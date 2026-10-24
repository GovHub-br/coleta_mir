FROM sinapir-joomla:php8.4.27

RUN set -eux; \
    a2enmod ssl rewrite headers; \
    mkdir -p /etc/apache2/ssl

COPY apache-oidc.conf /etc/apache2/sites-available/oidc.conf
COPY certgen.sh /usr/local/bin/certgen.sh

RUN set -eux; \
    chmod +x /usr/local/bin/certgen.sh; \
    a2dissite 000-default; \
    a2ensite oidc

EXPOSE 443
