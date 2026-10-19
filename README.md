# VPS 剩余价值计算器

仿照 [vps.ss](https://vps.ss/) 一比一复刻的 VPS / 服务器转让剩余价值计算器，支持 Docker 一键部署（适配 1Panel）。

> ⚠️ 本项目界面与交互复刻自 vps.ss，仅供个人学习与研究交流，请勿用于商业用途。

## 功能

- **按天折算剩余价值**：`剩余价值 = 续费金额 ÷ 付款周期天数 × 剩余天数 × 汇率`
- **实时汇率换算**：服务端每小时从公开汇率接口（open.er-api.com，备源 jsDelivr currency-api）拉取一次并缓存，支持 USD/CNY/EUR/GBP/JPY/HKD/TWD/KRW/SGD/AUD/CAD，也可勾选「自定义」手动填写汇率
- **卖家溢价双向反推**：填「实付卖家」自动算溢价，填「卖家溢价」自动算应付金额
- **Push 手续费分摊**：买家付 / 卖家付 / AA 平摊，支持多币种
- **5% 中介担保费核算**：同样支持三种分摊方式，计入买家总支出
- **一键复制**：纯文本 / Markdown 表格两种格式
- **SVG 分享图**：服务端生成 1200×630 结算图，相同参数复用同一张图
- **计算计数**：今日 / 累计计算次数

## 项目结构

```
├── html/                  # 站点根目录（Apache DocRoot）
│   ├── index.php          # 页面渲染（URL 参数服务端预填）
│   ├── api/
│   │   ├── rates.php      # GET  汇率 JSON
│   │   ├── count.php      # POST 计数自增（同源校验）
│   │   └── share.php      # POST 生成 SVG 分享图
│   ├── assets/            # 样式 / 脚本 / 图标 / flatpickr
│   └── share/             # 生成的分享图（卷挂载）
├── app/inc/               # 业务逻辑（DocRoot 之外）
│   ├── config.php         # 公共配置
│   ├── rates.php          # 汇率获取与缓存
│   ├── count.php          # 计数器
│   ├── calc.php           # 核心计算（与前端逻辑一致）
│   └── share_svg.php      # SVG 分享图生成
├── data/                  # 汇率缓存 / 计数（卷挂载）
├── Dockerfile
└── docker-compose.yml
```

## 本地运行

```bash
docker compose up -d --build
# 打开 http://127.0.0.1:8080
```

或使用 PHP 内置服务器（PHP 8.1+）：

```bash
cd html
php -S 127.0.0.1:8080    # 需将 ../app 提升为可访问路径，推荐直接用 Docker
```

## 1Panel 部署（Docker）

### 方式一：1Panel 编排（推荐）

1. SSH 登录服务器，克隆仓库：

   ```bash
   cd /opt
   git clone https://github.com/<你的用户名>/vps-value-calculator.git
   cd vps-value-calculator
   ```

2. （可选）修改 `docker-compose.yml` 中的宿主机端口（默认 `8080`）。

3. 打开 1Panel → **容器** → **编排** → **创建编排**：
   - 名称：`vps-calculator`
   - 路径：选择上面克隆的 `/opt/vps-value-calculator` 目录
   - 点击 **确认**，等待构建并启动。

4. 访问 `http://服务器IP:8080` 即可使用。

### 方式二：命令行直接部署

```bash
git clone https://github.com/<你的用户名>/vps-value-calculator.git
cd vps-value-calculator
docker compose up -d --build
```

### 建议的配套设置

- **域名 + HTTPS**：1Panel → **网站** → **创建网站** → **反向代理**，代理到 `http://127.0.0.1:8080`，再申请 Let's Encrypt 证书。反代时无需额外配置，程序已自动识别 `X-Forwarded-Proto` 生成 https 分享链接。
- **防火墙**：在 1Panel → **主机** → **防火墙** 放行对应端口（若使用反向代理可只放行 80/443）。

## 数据说明

- `vps-data` 卷（`/var/www/data`）：`rates.json` 汇率缓存、`count.json` 计数。删除后自动重建。
- `vps-share` 卷（`/var/www/html/share`）：生成的 SVG 分享图，可通过 `/share/<id>.svg` 直接访问。

## 环境要求

- Docker 20.10+ / Docker Compose v2
- PHP 8.1+（仅在使用非容器方式运行时）
- 出网访问 `open.er-api.com`（获取汇率；不可用时自动使用缓存或内置备用汇率）
