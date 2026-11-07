# ubuntu-php-deploy

**一条命令把 PHP 站点部署到 Ubuntu/Debian 的 nginx + php-fpm，幂等、可 dry-run、不写死任何项目和凭据。**

从「上传了代码但站点打不开」到可以访问，中间有十几步：装 nginx、装 php-fpm、写 server 块、配 fastcgi、改目录权限、放行端口、验证。这个脚本把这些步骤收成一条命令，并且可以重复执行——第二次跑只改有变化的部分。

## 实际输出

截图位：`docs/screenshot.png`（待补，终端工具优先看下面的真机输出）。

在没有 Linux 的环境里跑 `--dry-run` 的真实输出：

```
$ bash deploy.sh --app-dir /var/www/my-site --domain example.com --dry-run
[deploy] deploy.sh 1.0.0
[deploy] app dir   : /var/www/my-site
[deploy] web root  : /var/www/my-site/public
[deploy] listen    : 8080    server_name: example.com
[deploy] php       : auto    ext: mbstring pdo_sqlite
[deploy] 安装 nginx 与 PHP 组件…
  + apt-get update -qq
  + apt-get install -y -qq nginx php8.1-fpm php8.1-cli php8.1-sqlite3 php8.1-mbstring php8.1-curl php8.1-xml unzip curl
[deploy] 准备可写目录：/var/www/my-site/database
  + mkdir -p /var/www/my-site/database
  + chown -R www-data:www-data /var/www/my-site/database
  + chmod 775 /var/www/my-site/database
[deploy] 写 nginx 站点：/etc/nginx/sites-available/my-site
  + install nginx config (700 bytes) -> /etc/nginx/sites-available/my-site
  + ln -sfn /etc/nginx/sites-available/my-site /etc/nginx/sites-enabled/my-site
  + nginx -t
  + systemctl enable --now nginx
  + systemctl enable --now php8.1-fpm
  + systemctl reload nginx
[deploy] ufw 未启用（云厂商安全组需自行放行 8080 端口）
```

想先看 nginx 配置长什么样，不动系统：

```bash
bash deploy.sh --app-dir /var/www/my-site --domain example.com --php 8.3 --print-config
```

## 快速开始

前提：Ubuntu 20.04+ / Debian 11+，有 root，代码已经上传到服务器（脚本不负责传输代码）。

```bash
# 1) 拿到脚本
git clone https://github.com/x18967890089-cell/ubuntu-php-deploy.git
cd ubuntu-php-deploy

# 2) 先看计划（不需要 root，什么都不改）
bash deploy.sh --app-dir /var/www/my-site --domain example.com --dry-run

# 3) 真跑
sudo bash deploy.sh --app-dir /var/www/my-site --domain example.com

# 4) 本机自检会打印 HTTP 状态码；外网访问记得放行云安全组端口
curl -I http://127.0.0.1:8080/
```

默认监听 **8080**，不占 80 端口——服务器上已有站点时不会把它顶掉。确认没问题后再换成 80 端口重跑一次：

```bash
sudo bash deploy.sh --app-dir /var/www/my-site --domain example.com --port 80
```

上 HTTPS（脚本刻意不碰证书，交给 certbot）：

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d example.com
```

### 参数

| 参数 | 默认 | 说明 |
|---|---|---|
| `--app-dir DIR` | 必填 | 站点目录，必须绝对路径 |
| `--port PORT` | `8080` | nginx 监听端口 |
| `--domain NAME` | `_` | `server_name`，不填则不限定域名 |
| `--php VER` | 自动探测 | 例如 `8.2`；探测顺序：已装 `php` 的版本 → apt 源里的 `php-fpm` |
| `--webroot DIR` | `public` | web 根相对 `--app-dir` 的子目录；传 `""` 表示站点目录本身就是 web 根 |
| `--site NAME` | 目录名 | nginx 站点文件名 |
| `--db-dir DIR` | `database` | 需要 `www-data` 可写的目录；传 `""` 跳过 |
| `--db-init FILE` | 无 | 相对 `--app-dir` 的初始化脚本，例如 `init_db.php`；文件不存在则跳过 |
| `--health PATH` | `/` | 部署后自检请求的路径 |
| `--ext "A B"` | `mbstring pdo_sqlite` | 必须存在的 PHP 扩展 |
| `--client-max BODY` | `32m` | `client_max_body_size` |
| `--print-config` | — | 只输出生成的 nginx 配置并退出 |
| `--dry-run` | — | 只打印要执行的命令，不改系统 |

所有参数也可以用同名环境变量给（`APP_DIR`、`PORT`、`DOMAIN`、`PHP_VER`…），适合塞进部署流水线。

退出码：`0` 成功 · `1` 参数或环境错误 · `2` 依赖缺失 · `3` 部署后自检失败（看 `/var/log/nginx/error.log`）。

### 它会做什么

1. `apt-get install nginx phpX-fpm phpX-cli phpX-sqlite3 phpX-mbstring phpX-curl phpX-xml`
2. 检查 `php -m` 里有你要求的扩展，PHP ≥ 8.0
3. 建 `--db-dir` 目录并 `chown www-data`
4. 写 `/etc/nginx/sites-available/<site>`：隐藏文件拒绝、`*.sqlite|*.db|*.sql/.md/.log/.bak/.env/.ini` 拒绝下载、`try_files` 走 `index.php`
5. 配置和现有文件一致就不重写；不一致才备份成 `.bak` 再覆盖
6. 首次运行时把 nginx 默认站点挪成 `default.bak`
7. `nginx -t` 通过才 enable，然后 `reload`
8. ufw 已启用时放行端口
9. 本机 `curl` 自检，2xx/3xx 才算成功

## 已知限制

- **只在 Ubuntu 24.04 上设计，本机没有 Linux 服务器，未经真机跑通**：目前验证的是 bash 语法、`--help`、`--print-config`、`--dry-run` 与参数校验。第一次在你服务器上执行请先 `--dry-run` 看命令，再真跑。
- 只管 nginx + php-fpm。Apache、Caddy、Docker、宝塔面板不适用。
- 不负责代码传输（git/scp/rsync 自己来），也不负责 DNS、备案、证书。
- 不做数据库安装（除了 PHP 的 sqlite 扩展），MySQL/PostgreSQL 要自己装。
- 不支持 SELinux（Debian/Ubuntu 默认 AppArmor）；RHEL 系请改脚本。
- 只回滚 nginx 配置（`.bak`），不回滚软件包安装。
- 一个站点一个 server 块；多域名/多站点请一个域名跑一次。
- 不处理 `client_max_body_size` 以外的性能调优（缓存、gzip、HTTP/2 交给 certbot 和后续调整）。

## 许可证

MIT，见 [LICENSE](LICENSE)。随意改，出问题开 issue。
