# BackblazeUploader

> 将 Typecho 附件上传到 Backblaze B2 的插件，支持上传、替换、删除与附件 URL 回调。

[![Version](https://img.shields.io/badge/version-v2.2.2-blue.svg?style=flat-square)](./README.md)
[![Typecho](https://img.shields.io/badge/Typecho-Plugin-167B94.svg?style=flat-square)](./README.md)
[![PHP](https://img.shields.io/badge/PHP-8.x-4F5B93.svg?style=flat-square)](./README.md)
[![License](https://img.shields.io/badge/许可证-木兰宽松第2版-red.svg)](./LICENSE)

**快速入口**

[功能特性](#功能特性) · [安装](#安装方式) · [配置](#配置项说明) · [FAQ](#常见问题) · [许可证](#许可证)


## 一览

- 项目名称：Typecho-Plugin-BackblazeUploader
- 插件名称：BackblazeUploader
- 作者：猫东东（Alex Xu）
- 当前版本：v2.2.2（2026-03-17）

## 功能特性

- 接管 Typecho 附件上传流程（`uploadHandle`）
- 支持附件替换（`modifyHandle`）
- 支持删除附件时同步删除 B2 文件（`deleteHandle`）
- 支持附件地址回调（`attachmentHandle`）
- 支持自定义访问域名（可用于 CDN）
- 支持存储路径前缀配置
- 支持上传/删除请求超时配置

## 运行要求

- Typecho（支持插件机制）
- PHP 8.x
- 服务器可访问 Backblaze B2 API

## 安装方式

1. 将插件目录命名为 `BackblazeUploader`
2. 上传到 Typecho 的 `/usr/plugins/` 目录
3. 进入后台 `控制台 -> 插件`，启用 `BackblazeUploader`
4. 点击插件设置，填写 Backblaze B2 配置

## 配置项说明

| 字段 | 说明 | 是否必填 | 示例 |
| --- | --- | --- | --- |
| `keyId` | Application Key ID | 必填 | `xxxxxx` |
| `applicationKey` | Application Key | 必填 | `xxxxxx` |
| `bucketId` | B2 Bucket ID | 必填 | `xxxxxx` |
| `bucketName` | B2 Bucket 名称 | 必填 | `my-bucket` |
| `domain` | 自定义域名（不含末尾 `/`） | 可选 | `https://cdn.example.com` |
| `path` | 存储路径前缀（建议以 `/` 结尾） | 可选 | `typecho/` |
| `timeOut` | 超时时间（秒） | 可选 | `30` |

## 上传路径与访问 URL

- 新上传文件路径格式：`<path>/<Y/md>/<crc32>.<ext>`
- 若 `domain` 为空，默认访问域名为：`https://f002.backblazeb2.com/file/<bucketName>`
- 返回 URL 形如：`<domain>/<filePath>`

## 更新日志

### v2.2.2（当前版本）

- 修复占位图部分读取插件信息的错误

### v2.2.1

- 添加占位图

### v2.2.0

- 适配 Typecho 1.3.0+

### v2.1.0

- 修改 URL 返回方式

### v2.0.0

- 轻量级重构，去掉官方 SDK 中多余功能
- 精简至只有核心上传和删除 API
- 优化代码结构，提高性能
- 增加超时配置选项
- 改进错误处理

## 使用建议

- 建议在 Backblaze B2 创建最小权限的专用 Key，不要直接使用高权限主密钥
- 需要公开访问附件时，请确认 Bucket 的访问策略与域名配置正确
- 自定义域名时请检查 CNAME 与证书配置

## 常见问题

1. 上传失败：检查 `keyId`、`applicationKey`、`bucketId`、`bucketName`，并确认服务器可访问 `https://api.backblazeb2.com`
2. 上传成功但无法访问：检查 Bucket 读权限与 `domain` 配置（包含协议头，不含末尾 `/`）
3. 删除附件未同步删除 B2 文件：旧附件若不是由本插件上传，可能缺少 `fileid`，无法执行 B2 删除

## 许可证

本项目采用木兰宽松许可证，第 2 版（MulanPSL-2.0）。详见 `LICENSE` 文件。
