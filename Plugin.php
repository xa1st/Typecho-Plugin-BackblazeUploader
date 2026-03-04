<?php
namespace TypechoPlugin\BackblazeUploader;

use Typecho\Plugin\PluginInterface;
use Typecho\Plugin\Exception;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Password;
use Typecho\Http\Client;
use Typecho\Common;
use Utils\Helper;
use Widget\Upload;
use Widget\Options;


// 防止直接运行
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 将Typecho附件上传至Backblaze B2存储
 * 
 * @package BackblazeUploader
 * @author 猫东东
 * @version 2.2.0
 * @link https://github.com/xa1st/Typecho-Plugin-BackblazeUploader
 */

class Plugin implements PluginInterface {
    /**
    * 激活插件方法,如果激活失败,直接抛出异常
    * 
    * @return void
    * @throws Typecho_Plugin_Exception
    */
    public static function activate() {
        \Typecho\Plugin::factory('Widget_Upload')->uploadHandle = [__CLASS__, 'uploadHandle'];
        \Typecho\Plugin::factory('Widget_Upload')->modifyHandle = [__CLASS__, 'modifyHandle'];
        \Typecho\Plugin::factory('Widget_Upload')->deleteHandle = [__CLASS__, 'deleteHandle'];
        \Typecho\Plugin::factory('Widget_Upload')->attachmentHandle = [__CLASS__, 'attachmentHandle'];
        return _t('插件已启用，请前往设置页面配置Backblaze B2账号信息');
    }
  
    /**
    * 禁用插件方法,如果禁用失败,直接抛出异常
    * 
    * @return void
    * @throws Typecho_Plugin_Exception
    */
    public static function deactivate() {
        return _t('插件已禁用');
    }
  
    /**
    * 获取插件配置面板
    * 
    * @param Typecho_Widget_Helper_Form $form 配置面板
    * @return void
    */
    public static function config(Form $form) {
        $keyId = new Text('keyId',  null,  '',  _t('应用密钥ID'),  _t('Backblaze B2的应用密钥ID'));
        $form->addInput($keyId->addRule('required', _t('必须填写应用密钥ID')));
    
        $applicationKey = new Password('applicationKey',  null,  '',  _t('应用密钥'),  _t('Backblaze B2的应用密钥') );
        $form->addInput($applicationKey->addRule('required', _t('必须填写应用密钥')));
    
        $bucketId = new Text('bucketId',  null,  '',  _t('存储桶ID'),  _t('Backblaze B2的存储桶ID') );
        $form->addInput($bucketId->addRule('required', _t('必须填写存储桶ID')));
    
        $bucketName = new Text('bucketName', null, '', _t('存储桶名称'), _t('Backblaze B2的存储桶名称'));
        $form->addInput($bucketName->addRule('required', _t('必须填写存储桶名称')));
    
        $domain = new Text('domain', null, '', _t('自定义域名'), _t('如果您使用了自定义域名，请填写您的域名，例如：https://typecho.com，不包含尾部斜杠'));
        $form->addInput($domain);
    
        $path = new Text('path', null, 'typecho/', _t('存储路径'), _t('文件存储在存储桶中的路径前缀，以/结尾，例如：typecho/'));
        $form->addInput($path);

        $timeOut = new Text(
            'timeOut',
            NULL,
            '30',
            _t('超时时间（秒）'),
            _t('上传文件超时时间，单位为秒，默认为30秒。')
        );
        $form->addInput($timeOut->addRule('required', _t('请求超时时间')));
    }
  
    /**
    * 个人用户的配置面板
    * 
    * @param Typecho_Widget_Helper_Form $form
    * @return void
    */
    public static function personalConfig(Form $form) {
        // 暂无个人配置项
    }
  
    /**
    * 上传文件处理函数
    * 
    * @param array $file 上传的文件
    * @param bool $modify 是否为修改
    * @return array|bool
    */
    public static function uploadHandle($file, $modify = false) {
        // 获取上传文件
        if (empty($file['name'])) return false;    
        // 如果修改，则判定一下file['path']是否为空
        if ($modify && empty($file['path'])) return false;
        // 校验扩展名
        $ext = self::getSafeName($file['name']);
        // 验证可上传文件类型
        if (!Upload::checkFileType($ext))  throw new Exception('不允许上传的文件类型');
        // 获取插件配置
        $options = Helper::options()->plugin('BackblazeUploader');
        // 完全路径
        $fileName = $modify ? $file['path'] : (rtrim($options->path, '/') . '/' . date('Y/md/') . sprintf('%u', crc32(uniqid())) . '.' . $ext);
        // 上传到Backblaze.com
        $uploaded = self::uploadToBackblaze($file['tmp_name'], $fileName, $options);
        // 上传失败抛出错误
        if (!$uploaded) throw new Exception('上传失败');
        // 路径
        $domain = empty($options->domain) ? "https://f002.backblazeb2.com/file/{$options->bucketName}" : $options->domain;
        // 返回结果
        return [
            'name' => $file['name'], 
            'path' => $fileName,  // 这个直接用于
            'url' => $domain . '/' . $fileName,
            'size' => $uploaded['contentLength'], 
            'type' => str_replace('image/', '', $uploaded['contentType']),
            'mime' => @Common::mimeContentType($file['tmp_name']),
            'fileid' => $uploaded['fileId']
        ];
    }
  
    /**
    * 修改文件处理函数
    * 
    * @param array $content 文件相关信息
    * @param string $file 文件完整路径
    * @return string|bool
    */
    public static function modifyHandle($content, $file) {
        // 把旧文件的路径给新文件
        $file['path'] = $content['attachment']->path;
        // 再上传新文件
        return self::uploadHandle($file, true);
    }
  
    /**
    * 删除文件
    * 
    * @param string $path 文件路径
    * @return bool
    */
    public static function deleteHandle(array $content) {
        // 强行获取，如果为空也不报错
        $fileid = @$content['attachment']->fileid ?? '';
        // 强行获取，如果为空也不报错
        $author = @$content['attachment']->author ?? '';
        // 如果文件不存在且不是这个插件上传的，则直接返回
        if (empty($fileid) && $author != 'BackblazeUploader') return false;
        // 获取插件配置
        $options = Helper::options()->plugin('BackblazeUploader');
        // 获取授权信息
        $auth = self::getBackblazeAuth($options->keyId, $options->applicationKey);
        // 授权失败则直接返回
        if (!$auth) return false;
        // 开始删除文件
        try {
            $client = Client::get();
            $client->setHeader('Authorization', $auth['authorizationToken'])
                ->setHeader('Content-Type', 'application/json')
                ->setData(json_encode(['fileName' => $content['attachment']->path, 'fileId' => $fileid]))
                ->setTimeout(intval($options->timeout) <= 0 ? 30 : $options->timeout)
                ->setMethod(Client::METHOD_POST)
                ->send($auth['apiUrl'] . '/b2api/v2/b2_delete_file_version');
            // 删除成功
            if ($client->getResponseStatus() != 200) throw new Exception($client->getResponseBody());
            // 返回true
            return true;
        } catch (Exception $e) {
            throw new Exception('BackblazeUploader: 删除文件失败: ' . $e->getMessage());
        }
    }
  
    /**
    * 获取实际文件网址
    * 
    * @param array|Typecho::Config $content 文件相关信息 1.3以内的版本是array，1.3以上的是Typecho::Config
    * @return string
    */
    public static function attachmentHandle(Mixed $content) {
        // 判定参数类型
        if ($content instanceof \Typecho\Config) {
            // 说明是1.3.0以上版本
            $attachment = $content->toArray();
        } else {
            // 旧版本中是数组
            $attachment = $content['attachment'] ?? [];
        }
        // 如果存在url，则直接返回url
        if($attachment['url']) return $attachment['url'];
        // 返回本地图片地址
        return Common::url($attachment['path'], Options::alloc()->siteUrl);
    }
  
    /**
    * 上传文件到Backblaze B2
    * 
    * @param string $file 本地文件路径
    * @param string $target 目标存储路径
    * @return bool
    */
    private static function uploadToBackblaze($filePath, $fileTargetPath, $options = []) {
        // 检查配置
        if (empty($options->keyId) || empty($options->applicationKey)) throw new Exception('BackblazeUploader: 缺少必需的配置信息');
        // 获取授权信息
        $authResponse = self::getBackblazeAuth($options->keyId, $options->applicationKey);
        // 判定响应
        if (!$authResponse || !isset($authResponse['authorizationToken'])) throw new Exception('授权失败');
        // 获取上传URL
        try {
            $client = Client::get();
            $client->setHeader('Authorization', $authResponse['authorizationToken'])
                    ->setHeader('Content-Type', 'application/json')
                    ->setData(json_encode(['bucketId' => $options->bucketId]))
                    ->setTimeout(10)
                    ->setMethod(Client::METHOD_POST)
                    ->send($authResponse['apiUrl'] . '/b2api/v2/b2_get_upload_url');
            // 处理返回值
            if ($client->getResponseStatus() != 200) throw new Exception($client->getResponseBody());
            // 拿到上传相关信息
            $uploadAuth = json_decode($client->getResponseBody(), true);
        } catch (Exception $e) {
            throw new Exception('BackblazeUploader: 获取上传地址失败: ' . $e->getMessage());
        }
        // 检查文件是否存在
        if (!file_exists($filePath) || !is_readable($filePath)) throw new Exception('BackblazeUploader: 文件不存在或不可读: ' . $filePath);    
        // 获取文件内容 
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) throw new Exception('BackblazeUploader: 无法读取文件内容: ' . $filePath);
        // 上传
        try {
            $client = Client::get();
            $client->setHeader('Authorization', $uploadAuth['authorizationToken'])
                ->setHeader('X-Bz-File-Name', urlencode($fileTargetPath))
                ->setHeader('Content-Type', Common::mimeContentType($filePath))
                ->setHeader('X-Bz-Content-Sha1', sha1_file($filePath))
                ->setHeader('X-Bz-Info-Author', 'BackblazeUploader')
                ->setTimeout(intval($options->timeout) <= 0 ? 30 : $options->timeout)
                ->setData($fileContent)
                ->setMethod(Client::METHOD_POST)
                ->send($uploadAuth['uploadUrl']);
            $status = $client->getResponseStatus();
            // 抛出错误
            if ($status != 200) throw new Exception ('BackblazeUploader: 上传失败，HTTP状态码: ' . $status . ', 响应: ' . $client->getResponseBody());
            // 返回响应
            return json_decode($client->getResponseBody(), true);
        } catch (Exception $e) {
            throw new Exception('BackblazeUploader: 上传异常: ' . $e->getMessage());
        }
    }
    
    /**
    * 获取Backblaze B2认证信息
    * 
    * @param string $keyId 应用密钥ID
    * @param string $applicationKey 应用密钥
    * @return array|bool
    */
    private static function getBackblazeAuth(string $applicationKeyId, string $applicationKey) {
        // 发起请求
        try {
            $client = Client::get();
            $client->setHeader('Authorization', 'Basic ' . base64_encode($applicationKeyId . ':' . $applicationKey))
                ->setMethod(Client::METHOD_GET)
                ->setTimeout(10)
                ->send('https://api.backblazeb2.com/b2api/v2/b2_authorize_account');
            // 获取响应
            if ($client->getResponseStatus() != 200) throw new Exception($client->getResponseBody());
            // 解析响应
            return json_decode($client->getResponseBody(), true);
        } catch (Exception $e) {
            throw new Exception('BackblazeUploader: 上传异常: ' . $e->getMessage());
        }  
    }

    /**
    * 获取安全的文件名
    * 
    * @param string $name 文件名
    * @return string
    */
    private static function getSafeName(string $name): string {
        $name = str_replace(['"', '<', '>'], '', $name);
        $name = str_replace('\\', '/', $name);
        $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return $ext;
    }
}