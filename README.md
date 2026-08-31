# USP AI Services (Portal & API)

[![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com/)
[![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net/)
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE)

Plataforma centralizada de serviços e microsserviços baseados em Inteligência Artificial . O sistema oferece tanto uma **interface web (playground)** para uso direto quanto uma **API RESTful** para integração com sistemas.

---

## 🛠️ Módulos de Serviços

### 1. Formatação e Normalização Bibliográfica
Ajuste e padronização automática de referências bibliográficas de acordo com normas acadêmicas (ABNT NBR 6023, APA, etc.), com tratamento inteligente de ruídos, autores, intervalos de páginas e extração de dados para consulta ao catálogo Dedalus.


---

## 🚀 Arquitetura e Rotas

A aplicação é dividida em duas camadas principais no mesmo ecossistema:

* **Interface Web (Playground/Interface Pública):** `https://localhost:8000/bibliografia`
* **API REST (Integração entre Sistemas):** `https://localhost:8000/api/v1/...`
* **Documentação Swagger/OpenAPI:** `https://localhost:8000/docs`

---

## 💻 Instalação e Configuração Local

### Pré-requisitos
* PHP >= 8.1
* Composer
* Banco de Dados (MySQL / PostgreSQL / SQLite)

### Passo a Passo

* Inicio

```bash
    git clone git@github.com:uspdev/ai-services ai-services
    cd ai-services
    composer update
    cp .env.example .env
    php artisan key:generate

```

* Ajustes o .env, incluindo banco de dados

```bash
    php artisan migrate
```

## Testes

    php artisan dusk

### Testando envio de e-mails utilizando a plataforma Mailtrap

Utilizando a plataforma [Mailtrap](https://mailtrap.io/) é possível capturar os e-mails enviados sem que estes cheguem à caixa de entrada dos destinatários, possibilitando assim testar e analisar o envio de e-mails antes de se colocar em produção.

__Como Utilizar__
    
Após criar e entrar com uma conta na plataforma, é possível gerar as credenciais para o envio de e-mail no sistema utilizado, no caso do Laravel as credenciais seriam semelhantes à figura a seguir:

![image](https://user-images.githubusercontent.com/47902146/206538191-1b75750d-819b-4bc6-a8cf-efd7b8bf993b.png)

Assim, basta substituir tais credenciais no `.env` do projeto e enviar os e-mails normalmente que estes serão capturados na caixa de entrada do Mailtrap, sem serem enviados aos seus destinatários.

## Histórico

* 31/08/2026
    - Primeira versão

---

### Em produção

Para receber as últimas atualizações do sistema rode:

```sh
git pull
composer install --no-dev
php artisan migrate
```

### Senha única

Cadastre uma nova URL no configurador de senha única utilizando o caminho https://seu_app/callback. Guarde o callback_id para colocar no arquivo .env.

### Banco de dados

* DEV

    `php artisan migrate:fresh --seed`

* Produção

    `php artisan migrate`

## Problemas e soluções

Alguma dica de como resolver problemas comuns?
