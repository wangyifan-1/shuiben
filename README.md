# 顶峰永磁高速深井泵 IoT Explorer PHP 管理项目

这是一个轻量原生 PHP 项目，用于通过腾讯云 IoT Explorer 管理和控制“顶峰永磁高速深井泵”。项目提供：

- 泵设备档案管理：登记 `ProductId`、`DeviceName`、安装位置、最高频率等信息。
- Web 控制台：启动、停止、设定目标频率、切换运行模式、故障复位、读取设备数据。
- REST API：便于接入上位机、调度系统或企业后台。
- 腾讯云 TC3-HMAC-SHA256 签名客户端：直接调用 IoT Explorer OpenAPI，无需额外 SDK。
- 可配置物模型标识符：适配实际产品定义中的属性 identifier。

> 安全提示：该项目会下发真实设备控制指令。生产部署时请务必配置 `APP_API_TOKEN`、HTTPS、网络访问控制和操作审计。

## 目录结构

```text
config/config.php             应用、腾讯云和物模型配置
public/index.php              Web 控制台和 API 入口
src/Domain                    泵设备仓库、控制服务和控制器
src/Http                      请求、响应和路由
src/TencentCloud              IoT Explorer OpenAPI 客户端
src/Support                   配置、异常和 .env 加载器
storage/pumps.json            本地泵设备档案存储
```

## 环境要求

- PHP 8.1+
- PHP 扩展：`json`、`curl`
- 可选：Composer（用于 PSR-4 自动加载；未安装 Composer 时项目入口会使用内置自动加载器）

## 快速启动

1. 复制环境变量文件：

   ```bash
   cp .env.example .env
   ```

2. 编辑 `.env`：

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_API_TOKEN=请替换为足够长的随机令牌

   TENCENTCLOUD_SECRET_ID=AKID...
   TENCENTCLOUD_SECRET_KEY=...
   TENCENTCLOUD_REGION=ap-guangzhou
   TENCENTCLOUD_IOT_ENDPOINT=iotexplorer.tencentcloudapi.com
   ```

3. 确认 IoT Explorer 产品已完成物模型定义，并在 `config/config.php` 中把以下 identifier 改成实际物模型属性：

   ```php
   'thing_model' => [
       'power_switch' => 'power_switch',
       'target_frequency' => 'target_frequency',
       'work_mode' => 'work_mode',
       'fault_reset' => 'fault_reset',
   ],
   ```

4. 启动本地服务：

   ```bash
   php -S 0.0.0.0:8080 -t public
   ```

5. 打开 `http://localhost:8080`，输入 API Token，登记泵设备的 `ProductId` 和 `DeviceName` 后即可操作。

## 使用 MAMP / Apache 访问

推荐把 MAMP 的站点根目录直接指向项目的 `public/` 目录：

```text
/path/to/shuiben/public
```

如果你的本地域名是 `local.shuibeng.com`，端口是 `7888`，健康检查地址应为：

```text
http://local.shuibeng.com:7888/api/health
```

如果访问 `/api/health` 返回 404，请依次确认：

1. MAMP 的 Apache 已启用 `mod_rewrite`。
2. 虚拟主机或 MAMP Document Root 指向 `public/`，或至少指向项目根目录。
3. Apache 允许读取 `.htaccess`，虚拟主机配置里需要类似：

   ```apache
   <Directory "/path/to/shuiben">
       AllowOverride All
       Require all granted
   </Directory>
   ```

4. 修改配置后重启 MAMP Apache。

项目已包含根目录 `.htaccess` 和 `public/.htaccess`，用于把 `/api/*` 转发到 `public/index.php`。

## 推荐物模型属性

如果腾讯云产品尚未建模，可以参考以下属性。最终名称以 IoT Explorer 控制台中的 identifier 为准：

| 功能 | identifier 示例 | 类型 | 说明 |
| --- | --- | --- | --- |
| 启停 | `power_switch` | int/bool | `1` 启动，`0` 停止 |
| 目标频率 | `target_frequency` | float/int | 单位 Hz |
| 运行模式 | `work_mode` | enum/string | 如 `auto`、`manual` |
| 故障复位 | `fault_reset` | int/bool | 写入 `1` 触发复位 |

## REST API

所有 API 都返回 JSON。配置 `APP_API_TOKEN` 后，需要发送请求头：

```http
X-Api-Token: 你的令牌
```

也可以使用：

```http
Authorization: Bearer 你的令牌
```

### 健康检查

```http
GET /api/health
```

### 设备管理

```http
GET /api/pumps
POST /api/pumps
GET /api/pumps/{id}
PUT /api/pumps/{id}
DELETE /api/pumps/{id}
```

创建设备示例：

```bash
curl -X POST http://localhost:8080/api/pumps \
  -H 'Content-Type: application/json' \
  -H 'X-Api-Token: 你的令牌' \
  -d '{
    "id": "pump-001",
    "name": "顶峰永磁高速深井泵 1#",
    "product_id": "PRODUCT_ID",
    "device_name": "DEVICE_NAME",
    "location": "1# 深井",
    "max_frequency_hz": 400
  }'
```

### 读取设备数据

```http
GET /api/pumps/{id}/data
```

该接口调用腾讯云 IoT Explorer `DescribeDeviceData`。

### 控制设备

```http
POST /api/pumps/{id}/control
```

该接口调用腾讯云 IoT Explorer `ControlDeviceData`，并将命令转换为物模型属性下发。

启动：

```json
{ "command": "start" }
```

停止：

```json
{ "command": "stop" }
```

设定目标频率：

```json
{ "command": "set_frequency", "frequency_hz": 50 }
```

切换模式：

```json
{ "command": "set_mode", "mode": "auto" }
```

故障复位：

```json
{ "command": "reset_fault" }
```

直接下发原始物模型属性：

```json
{
  "command": "raw",
  "properties": {
    "power_switch": 1,
    "target_frequency": 60
  }
}
```

## 部署建议

- `storage/pumps.json` 需要 Web 服务进程可读写。
- 将 `public/` 设置为 Web 根目录，不要直接暴露项目根目录。
- 生产环境启用 HTTPS，并将控制台放在内网、VPN 或零信任网关之后。
- 腾讯云访问密钥建议使用最小权限子账号，只授予 IoT Explorer 相关 API 权限。
- 对水泵启停、频率调整等危险操作增加现场联锁、权限审批和日志审计。
