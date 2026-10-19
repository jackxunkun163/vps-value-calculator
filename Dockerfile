FROM php:8.3-apache

LABEL org.opencontainers.image.title="VPS 剩余价值计算器"
LABEL org.opencontainers.image.description="VPS/服务器转让剩余价值计算器（复刻自 vps.ss），支持汇率换算、溢价反推、Push/中介费分摊与 SVG 分享图"

COPY html/ /var/www/html/
COPY app/ /var/www/app/

# data：汇率缓存与计数（挂载卷持久化）；share：生成的 SVG 分享图（需可写且可访问）
RUN mkdir -p /var/www/data /var/www/html/share \
    && chown -R www-data:www-data /var/www/data /var/www/html/share

EXPOSE 80
