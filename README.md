# nginx-phpfpm-lxc
Loadbalancing with Nginx + php-fpm in lxc containers.

Балансировка нагрузки с помощью Nginx + php-fpm в контейнерах lxc.

![изображение](https://github.com/cloaksocks/nginx-phpfpm-lxc/assets/157986562/fc540c50-803b-42ed-8f6d-4c0de6bc49f2)


## Задание:

Есть готовый кластер с PostgreSQL, который работает в LXC контейнере container-1, в postgres находится БД financial_exchange, c таблицей stocks, к которой есть доступ с любого ip адреса для пользователя stocks_viewer, c паролем password. Ваша задача включает в себя следующее:

1. Установка и настройка php-fpm, в отдельном контейнере, для доступа к таблице stocks в бд financial_exchange.

2. Cоздание PHP скрипта для отображения данных из таблицы в Postgres

3. Репликация этого контейнера php-fpm, для создания пула из 4 контейнеров, для балансировки нагрузки.
    
4. Установка и настройка Nginx в отдельном контейнере, для проксирования запросов в созданный пул инстансов PHP-FPM.
    
5. Проверка работы сборки.

## Шаги:

### 1. Запуск контейнеров с nginx и php-fpn на базе OS Ubuntu:
  - Запускаем контейнеры lxc для php-fpm (container-2) и nginx (container-3)
```
lxc launch ubuntu-minimal:22.04 container-2
lxc launch ubuntu-minimal:22.04 container-3
```

  - установка PHP-FPM в container-2
```
lxc exec container-2 -- apt update
lxc exec container-2 -- apt install -y php-fpm php-pgsql
```

  - установка Nginx в container-3:
```
lxc exec container-3 -- apt update
lxc exec container-3 -- apt install -y nginx 
```


### 2. Настройка PHP-FPM в container-2
- провалимся в контейнер
```
lxc exec container-2 bash
```
  - в /etc/php/8.1/fpm/php.ini выставим значение cgi.fix_pathinfo=0 чтобы предотвратить возможные уязвимости:
```
sed -i s/";cgi.fix_pathinfo=1"/"cgi.fix_pathinfo=0"/ /etc/php/8.1/fpm/php.ini 
```


### 3. Cоздание PHP скрипта для отображения данных из таблицы в Postgres
  - создадим каталог и поместим в него PHP скрипты:
```
mkdir -m 755 -p /var/www/html
echo "<?php include 'stocks.php';?>" > /var/www/html/index.php
touch /var/www/html/stocks.php
```
в stocks.php:
```
<?php
echo '<h4>financial_exchange stocks </h4>';
$host = '10.243.60.168';
$port = '5433';
$dbname = 'financial_exchange';
$user = 'stocks_viewer';
$password = 'password';

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
  if (!$conn){
  echo "connection error \n";
}

$result = pg_query($conn, "SELECT * FROM stocks");
if (!$result) {
  echo "An error occurred.\n";
  exit;
}

while ($row = pg_fetch_row($result)) {
  echo "stock_id: $row[0]  company_id: $row[1]  price: $row[2]  quantity: $row[3]  timestamp: $row[4]";
  echo "<br />\n";
}

?>
```
```
chown -R www-data: /var/www/html
```
  - Отредактируем конфигурационный файл www.conf для прослушивания соединений по tcp socket:
	в /etc/php/8.1/fpm/pool.d/www.conf, пропишем:
```
listen = 0.0.0.0:9000
```
```
systemctl restart php8.1-fpm
```


### 4. Реплицикация container-2
```
poweroff
```
  - На хосте ubuntu server:
```
lxc copy container-2 container-2fpm1 && lxc start container-2fpm1
lxc copy container-2 container-2fpm2 && lxc start container-2fpm2
lxc copy container-2 container-2fpm3 && lxc start container-2fpm3
```


### 5. Настройка Nginx в container-3
```
lxc exec container-3 bash
```
  - в /etc/nginx/sites-available/default: укажем параметры для проксирования запросов в созданный пул PHP-FPM:

```
upstream phpfpm {
	ip_hash;

	server container-2:9000;
	server container-2fpm1:9000;
	server container-2fpm2:9000;
	server container-2fpm3:9000;
}

server {
	listen 80 default_server;
	listen [::]:80 default_server;

	charset utf-8;
	server_name _;

	location / {
		try_files $uri $uri/ /index.php?$args;
	}

	location ~ \.php$ {
		fastcgi_split_path_info ^(.+\.php)(/.+)$;
		fastcgi_pass phpfpm;
		root /var/www/html;
		fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
		fastcgi_index index.php;
		include fastcgi.conf;
	}

	# Deny all attempts to access hidden files such as .htaccess, .htpasswd, .DS_Store (Mac).
	# Keep logging the requests to parse later (or to pass to firewall utilities such as fail2ban)
	location ~ /\. {
		deny all;
	}

	# Directives to send expires headers and turn off 404 error logging.
	location ~* \.(js|css|png|jpg|jpeg|gif|ico)$ {
		expires 24h;
		log_not_found off;
	}
	
}
```

```
systemctl restart nginx
ip -c a
  получим
	>ip_адрес_контейнера
```
 
### 6. Проверка работы балансировщика
  - откроем в браузере http://ip_адрес_контейнера/index.php, где видим таблицу stocks, базы данных financial_exchange:

![изображение](https://github.com/cloaksocks/nginx-phpfpm-lxc/assets/157986562/306603e4-6e6e-4b1b-99ff-68eed7edac96)

