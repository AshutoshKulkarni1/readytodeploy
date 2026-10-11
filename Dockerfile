FROM php:8.3-cli

# Install system dependencies for PHP and Python
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libonig-dev \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    python3 \
    python3-pip \
    python3-venv \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mysqli \
        curl \
        mbstring \
        zip \
        gd \
        intl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Install Python requirements in a virtual environment
COPY src/python/requirements.txt /app/src/python/requirements.txt
RUN python3 -m venv /opt/venv && \
    /opt/venv/bin/pip install --no-cache-dir -r /app/src/python/requirements.txt

ENV PATH="/opt/venv/bin:$PATH"

COPY . .

RUN chmod +x /app/start.sh

EXPOSE 8000

CMD ["./start.sh"]
