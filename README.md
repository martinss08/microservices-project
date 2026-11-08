# Mini Microservices Project

Projeto de estudo de arquitetura de microsserviços com Laravel, RabbitMQ e Redis. Demonstra comunicação assíncrona entre serviços, controle de idempotência e processamento de eventos com deduplicação distribuída.

## Sumário

- [Arquitetura](#arquitetura)
- [Serviços](#serviços)
- [Stack](#stack)
- [Fluxo de dados](#fluxo-de-dados)
- [Pré-requisitos](#pré-requisitos)
- [Como rodar](#como-rodar)
- [Endpoints da API](#endpoints-da-api)
- [Variáveis de ambiente](#variáveis-de-ambiente)
- [Estrutura de pastas](#estrutura-de-pastas)

---

## Arquitetura

```
Cliente HTTP
    │
    ▼
Order Service (porta 8003)
    │  ├─ Salva pedido no banco (MySQL)
    │  ├─ Controle de idempotência (Redis)
    │  └─ Publica eventos no RabbitMQ
    │
    ▼
RabbitMQ — exchange: orders.exchange (direct)
    │        queue:    orders.queue
    │        routing keys: order.created, order.confirmed
    ▼
Notification Service (porta 8004)
    │  ├─ Consome mensagens da fila
    │  ├─ Distributed lock (Redis)
    │  └─ Deduplicação de eventos (Redis TTL)
```

---

## Serviços

### Order Service

API REST responsável por receber e persistir pedidos. A cada pedido criado, publica dois eventos no RabbitMQ:

| Evento | Routing Key |
|---|---|
| Pedido criado | `order.created` |
| Pedido confirmado | `order.confirmed` |

**Camadas internas:**

| Camada | Descrição |
|---|---|
| `Http/Controllers` | Recebe requisições HTTP e delega para o serviço |
| `Http/Requests` | Validação dos dados de entrada (`PedidosRequest`) |
| `DTO` | Transferência tipada de dados (`CreatePedidoDTO`) |
| `Service` | Regra de negócio, idempotência e publicação de eventos |
| `Infrastructure/RabbitMQ` | Publicação de mensagens no broker |
| `Infrastructure/Redis` | Controle de idempotência e cache de resultados |
| `Models` | Modelo Eloquent `Pedidos` |

### Notification Service

Consumidor de fila responsável por processar eventos de pedidos. Roda como um comando Artisan (`app:consumer-rabbti-m-q`) em loop contínuo.

**Comportamento:**

- Conecta ao RabbitMQ e escuta a fila `orders.queue`
- Verifica se o evento já foi processado (`processed_event:{id}` no Redis)
- Adquire distributed lock (`lock:{id}` no Redis, TTL 60s) para evitar processamento concorrente
- Processa o evento conforme a routing key (`order.created` / `order.confirmed`)
- Marca o evento como processado com TTL de 3600 segundos
- Faz `basic_ack` em caso de sucesso e `basic_nack` (sem requeue) em caso de erro

---

## Stack

| Tecnologia | Versão | Papel |
|---|---|---|
| PHP | 8.3 | Runtime |
| Laravel | 11.x | Framework web / CLI |
| RabbitMQ | 3 (management) | Message broker |
| Redis | latest | Idempotência, lock e cache |
| Docker / Compose | — | Orquestração de containers |
| php-amqplib | ^3.7 | Client AMQP para PHP |

---

## Fluxo de dados

```
POST /api/pedidos
    │
    ├─ Valida campos (PedidosRequest)
    ├─ Monta DTO (CreatePedidoDTO)
    ├─ Redis SET NX (idempotency_key, TTL 1500s) ──► duplicata? retorna erro
    ├─ Persiste pedido no banco
    ├─ Redis SETEX order:{id} (resultado, TTL 3600s)
    ├─ Publica order.created  → orders.exchange → orders.queue
    └─ Publica order.confirmed → orders.exchange → orders.queue

Notification Service (loop)
    │
    ├─ Recebe mensagem da orders.queue
    ├─ Redis EXISTS processed_event:{id} ──► já processado? ack e ignora
    ├─ Redis SET NX lock:{id} (TTL 60s) ──► em processamento? nack + requeue
    ├─ Processa conforme routing key
    ├─ Redis SETEX processed_event:{id} (TTL 3600s)
    └─ basic_ack
```

---

## Pré-requisitos

- [Docker](https://www.docker.com/) e Docker Compose
- [Composer](https://getcomposer.org/) (para instalar dependências localmente, se necessário)

---

## Como rodar

### 1. Subir a infraestrutura

```bash
docker-compose up -d
```

Isso inicia: `rabbitmq`, `redis`, `order-service` e `notification-service`.

### 2. Instalar dependências (dentro dos containers)

```bash
docker-compose exec order-service composer install
docker-compose exec notification-service composer install
```

### 3. Configurar variáveis de ambiente

Copie os arquivos de exemplo e preencha as variáveis (veja a seção [Variáveis de ambiente](#variáveis-de-ambiente)):

```bash
cp order-service/.env.example order-service/.env
cp notification-service/.env.example notification-service/.env
```

### 4. Rodar as migrations (Order Service)

```bash
docker-compose exec order-service php artisan migrate
```

### 5. Iniciar o consumidor (Notification Service)

```bash
docker-compose exec notification-service php artisan app:consumer-rabbti-m-q
```

---

## Endpoints da API

Base URL: `http://localhost:8003`

### Listar pedidos

```
GET /api/pedidos
```

**Resposta:**
```json
{
    "status": true,
    "message": [ ... ]
}
```

### Criar pedido

```
POST /api/pedidos
Content-Type: application/json
```

**Body:**
```json
{
    "Idempotency_key": "chave-unica-da-requisicao",
    "name": "João Silva",
    "email": "joao@email.com",
    "quantity": 2,
    "price": 49.90
}
```

| Campo | Tipo | Regras |
|---|---|---|
| `Idempotency_key` | string | obrigatório — garante unicidade da requisição |
| `name` | string | obrigatório, 3–100 caracteres |
| `email` | string | obrigatório, formato e-mail válido, máx. 150 chars |
| `quantity` | integer | obrigatório, mínimo 1 |
| `price` | decimal | obrigatório, até 2 casas decimais |

**Resposta de sucesso (200):**
```json
{
    "status": true,
    "message": "Pedido feito com sucesso",
    "data": { "id": 1, "name": "João Silva", ... }
}
```

**Resposta para requisição duplicada (exception):**
```
Requisição duplicada
```

---

## Variáveis de ambiente

Ambos os serviços precisam de um arquivo `.env`. As variáveis relevantes são:

```env
# Banco de dados (Order Service)
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=orders
DB_USERNAME=root
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
```

> O painel de administração do RabbitMQ fica disponível em `http://localhost:15672` (usuário/senha: `guest/guest`).

---

## Estrutura de pastas

```
microservices-mini-project/
├── docker-compose.yml
├── composer.json
├── order-service/                  # API REST de pedidos
│   ├── app/
│   │   ├── DTO/
│   │   │   └── CreatePedidoDTO.php
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   └── PedidosController.php
│   │   │   └── Requests/
│   │   │       └── PedidosRequest.php
│   │   ├── Infrastructure/
│   │   │   ├── RabbitMQ/
│   │   │   │   └── PublishedRabbitMQ.php
│   │   │   └── Redis/
│   │   │       └── RedisService.php
│   │   ├── Models/
│   │   └── Service/
│   │       └── PedidoService.php
│   ├── database/migrations/
│   ├── routes/api.php
│   └── Dockerfile
└── notification-service/           # Consumidor de eventos
    ├── app/
    │   └── Console/
    │       └── Commands/
    │           └── ConsumerRabbtiMQ.php
    └── Dockerfile
```
