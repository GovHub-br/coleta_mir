# Runtime do SINAPIR. O Joomla 6.2.0 vem do volume ./data/www.
# A tag oficial joomla só acompanha o PHP 8.4 flutuante; aqui o patch fica fixo.
FROM php:8.4.27-apache

RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		ghostscript \
	; \
	rm -rf /var/lib/apt/lists/*

RUN set -ex; \
	savedAptMark="$(apt-mark showmanual)"; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		libbz2-dev \
		libgmp-dev \
		libicu-dev \
		libfreetype6-dev \
		libjpeg-dev \
		libldap2-dev \
		libmemcached-dev \
		libmagickwand-dev \
		libonig-dev \
		libpq-dev \
		libpng-dev \
		libwebp-dev \
		libzip-dev \
	; \
	docker-php-ext-configure gd \
		--with-freetype \
		--with-jpeg \
		--with-webp \
	; \
	debMultiarch="$(dpkg-architecture --query DEB_BUILD_MULTIARCH)"; \
	docker-php-ext-configure ldap --with-libdir="lib/$debMultiarch"; \
	docker-php-ext-install -j "$(nproc)" \
		bz2 \
		bcmath \
		exif \
		gd \
		gmp \
		intl \
		ldap \
		mbstring \
		mysqli \
		pdo_mysql \
		pdo_pgsql \
		pgsql \
		zip \
	; \
	docker-php-ext-enable opcache; \
	pecl install imagick-3.8.1; \
	docker-php-ext-enable imagick; \
	rm -r /tmp/pear; \
	out="$(php -r 'exit(0);')"; \
	[ -z "$out" ]; \
	err="$(php -r 'exit(0);' 3>&1 1>&2 2>&3)"; \
	[ -z "$err" ]; \
	extDir="$(php -r 'echo ini_get("extension_dir");')"; \
	[ -d "$extDir" ]; \
	pecl install APCu-5.1.28; \
	pecl install memcached-3.4.0; \
	pecl install redis-6.3.0; \
	docker-php-ext-enable \
		apcu \
		memcached \
		redis \
	; \
	rm -r /tmp/pear; \
	apt-mark auto '.*' > /dev/null; \
	apt-mark manual $savedAptMark; \
	ldd "$extDir"/*.so \
		| awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); print so }' \
		| sort -u \
		| xargs -r dpkg-query --search \
		| cut -d: -f1 \
		| sort -u \
		| xargs -rt apt-mark manual; \
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
	rm -rf /var/lib/apt/lists/*; \
	! { ldd "$extDir"/*.so | grep 'not found'; }; \
	err="$(php --version 3>&1 1>&2 2>&3)"; \
	[ -z "$err" ]

RUN set -eux; \
	{ \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=2'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini; \
	{ \
		echo 'error_reporting = E_ERROR | E_WARNING | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_RECOVERABLE_ERROR'; \
		echo 'display_errors = Off'; \
		echo 'display_startup_errors = Off'; \
		echo 'log_errors = On'; \
		echo 'error_log = /dev/stderr'; \
		echo 'log_errors_max_len = 1024'; \
		echo 'ignore_repeated_errors = On'; \
		echo 'ignore_repeated_source = Off'; \
		echo 'html_errors = Off'; \
	} > /usr/local/etc/php/conf.d/error-logging.ini; \
	a2enmod rewrite expires headers

WORKDIR /var/www/html
