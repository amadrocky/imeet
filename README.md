# Starting the project with Lando

## System Requirements
Lando is designed to work on a wide range of computers. Here are some basic guidelines to ensure your Lando experience is as smooth as possible.  
Need to install [Lando](https://docs.lando.dev/getting-started/installation.html)?

## Docker Engine Requirements
Please also verify you meet the requirements needed to run our Docker engine backend. Note that the macOS and Windows Lando installer will install Docker for you if needed.

## Getting Started

1. Run `lando start` to build fresh images and start the project
2. Run `lando composer install` to install all dependencies
3. Run `lando npm install` to install node packages
4. Run `lando sf doctrine:migrations:migrate` to update migrations (the command `lando sf` replace `php bin/console`)

## Containers URLS
Once the project is started, you have many available endpoints.

APPSERVER NGINX : `http://imeet.lndo.site/`  
MAILHOG : `http://mailhog.imeet.lndo.site/`  
PHPMYADMIN : `http://localhost:32918`
