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

## Docker 镜像

镜像由 GitHub Actions 自动构建并发布，支持 `linux/amd64` 与 `linux/arm64`：

```
ghcr.io/jackxunkun163/vps-value-calculator:latest
```

直接运行（等价于拉取安装）：

```bash
docker run -d --name vps-calculator --restart unless-stopped \
  -p 8080:80 \
  ghcr.io/jackxunkun163/vps-value-calculator:latest
```

> 需要 Docker Hub（docker.io）上的镜像？见下方「发布到 Docker Hub」一节——配置两个仓库 Secret 后，CI 会自动双推，镜像地址变为 `<你的用户名>/vps-value-calculator`。

## 1Panel 部署

### 方式一：编排（推荐，无需克隆代码）

1. 打开 1Panel → **容器** → **编排** → **创建编排**：
   - 名称：`vps-calculator`
   - 来源选择**编辑内容**（或模板编辑），粘贴以下内容：

     ```yaml
     services:
       vps-calculator:
         image: ghcr.io/jackxunkun163/vps-value-calculator:latest
         container_name: vps-calculator
         restart: unless-stopped
         ports:
           - "8080:80"   # 左侧为宿主机端口，可按需修改
         volumes:
           - vps-data:/var/www/data         # 汇率缓存 / 计数数据
           - vps-share:/var/www/html/share  # 生成的 SVG 分享图

     volumes:
       vps-data:
       vps-share:
     ```

2. 点击**确认**，等待镜像拉取并启动，访问 `http://服务器IP:8080`。

> 也可使用仓库根目录的 `docker-compose.yml`（内容与上面一致），通过「编排 → 创建编排 → 指定路径」克隆后使用。

### 方式二：1Panel 容器界面直接拉镜像

1Panel → **容器** → **容器** → **创建容器**：

- 名称：`vps-calculator`
- 镜像：`ghcr.io/jackxunkun163/vps-value-calculator:latest`
- 端口映射：宿主机 `8080` → 容器 `80`
- 挂载卷：建议创建两个命名卷，分别挂载到 `/var/www/data`（汇率缓存/计数）与 `/var/www/html/share`（分享图），重建容器后数据不丢失

### 方式三：从源码构建

```bash
git clone https://github.com/jackxunkun163/vps-value-calculator.git
cd vps-value-calculator
docker compose -f docker-compose.build.yml up -d --build
```

### 建议的配套设置

- **域名 + HTTPS**：1Panel → **网站** → **创建网站** → **反向代理**，代理到 `http://127.0.0.1:8080`，再申请 Let's Encrypt 证书。反代时无需额外配置，程序已自动识别 `X-Forwarded-Proto` 生成 https 分享链接。
- **防火墙**：在 1Panel → **主机** → **防火墙** 放行对应端口（若使用反向代理可只放行 80/443）。

## 数据说明

- `vps-data` 卷（`/var/www/data`）：`rates.json` 汇率缓存、`count.json` 计数。删除后自动重建。
- `vps-share` 卷（`/var/www/html/share`）：生成的 SVG 分享图，可通过 `/share/<id>.svg` 直接访问。

## 发布到 Docker Hub（可选）

CI（`.github/workflows/docker-publish.yml`）默认把镜像推送到 ghcr.io。如需同步发布到 Docker Hub：

1. 登录 [Docker Hub](https://hub.docker.com/) → **Account Settings** → **Security** → **New Access Token**，权限选择 **Read & Write**，复制令牌。
2. 打开 GitHub 仓库 → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**，添加：
   - `DOCKERHUB_USERNAME`：Docker Hub 用户名
   - `DOCKERHUB_TOKEN`：上一步生成的访问令牌
3. 之后每次 push 到 `main`（或推送 `v*` 标签）会自动双推；也可在 **Actions** 页面手动 **Run workflow**。

配置完成后镜像地址为：`<你的DockerHub用户名>/vps-value-calculator:latest`，1Panel 中把 compose 的 `image:` 换成该地址即可。

## 环境要求

- Docker 20.10+ / Docker Compose v2
- PHP 8.1+（仅在使用非容器方式运行时）
- 出网访问 `open.er-api.com`（获取汇率；不可用时自动使用缓存或内置备用汇率）
