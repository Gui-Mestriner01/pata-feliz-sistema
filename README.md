# Pata Feliz — Sistema de agendamento

Sistema de agendamento e gestão do pet shop Pata Feliz: a página pública onde o
cliente pede horário e o painel onde o estabelecimento aprova, recusa ou sugere
remarcação — com aviso pronto para enviar no WhatsApp.

**PHP 8.1+ e MySQL/MariaDB.** Sem build, sem Composer, sem Node. Roda direto na
Hostinger.

---

## Instalação

### 1. Banco de dados

No painel da Hostinger, crie um banco MySQL e anote nome, usuário e senha.
Depois, no phpMyAdmin, importe o arquivo `banco/schema.sql`.

Ele cria as tabelas, os serviços de exemplo e o usuário inicial do painel.

### 2. Configuração

Copie o modelo e preencha com os dados do ambiente:

```bash
cp config/config.exemplo.php config/config.php
```

O `config/config.php` guarda as credenciais e **não é versionado** — cada
ambiente (sua máquina, a Hostinger) tem o seu. Ajuste:

```php
define('BD_NOME', 'seu_banco');
define('BD_USUARIO', 'seu_usuario');
define('BD_SENHA', 'sua_senha');

define('LOJA_WHATSAPP', '5511987654321'); // DDI + DDD + número, só dígitos
```

Se o sistema não ficar na raiz do domínio (ex.: `site.com.br/sistema`), ajuste
também:

```php
define('BASE_URL', '/sistema');
```

### 3. Envio dos arquivos

Suba a pasta inteira por FTP ou pelo gerenciador de arquivos.

### 4. Crie o seu acesso

Abra `seudominio.com.br/instalar.php` e preencha nome, e-mail e senha. Esse é
o usuário administrador do painel.

O sistema **não vem com senha de fábrica**: senha padrão em sistema publicado
é senha que qualquer pessoa conhece. O instalador se desliga sozinho depois do
primeiro usuário.

### 5. Apague o instalador

Remova o `instalar.php` do servidor. Depois é só entrar em
`seudominio.com.br/admin/login.php`.

---

## Como funciona

### Para o cliente

1. Acessa `publico/agendar.php` e escolhe o serviço e a data.
2. O sistema mostra **somente os horários realmente livres** daquele dia.
3. O pedido entra como *aguardando aprovação*.

### Para o pet shop

No painel, o pedido aparece em **Início** e em **Agendamentos**. Abrindo o
pedido, há três caminhos:

| Ação | O que acontece |
|---|---|
| **Aprovar** | Status vira *confirmado* e o horário fica reservado. |
| **Sugerir outro horário** | Status vira *remarcação sugerida*; o sistema só deixa escolher horários livres. Se o cliente aceitar, um botão move o agendamento para o novo horário. |
| **Recusar** | Status vira *recusado* (o motivo é obrigatório e entra na mensagem). |

Em todos os casos o sistema monta a **mensagem pronta para o cliente**, com o
texto certo para cada situação. É só conferir, ajustar se quiser e clicar em
*Abrir no WhatsApp*.

### Como os horários não se colidem

Cada serviço tem uma duração (banho 60 min, tosa 90 min…). O sistema calcula o
horário de término e considera ocupada qualquer faixa que se sobreponha a outro
atendimento *pendente* ou *confirmado*.

Quantos pets podem ser atendidos ao mesmo tempo é configurável em
**Agenda → Pets atendidos ao mesmo tempo** (padrão: 2). Além disso o sistema
respeita:

- dias e horário de funcionamento;
- antecedência mínima para agendar;
- bloqueios de agenda (feriado, almoço, manutenção);
- até quantos dias à frente aceita pedido.

A checagem é feita duas vezes — ao montar a lista de horários e de novo na hora
de gravar, dentro de uma transação —, então duas pessoas não conseguem pegar a
mesma vaga ao mesmo tempo.

---

## Estrutura

```
index.php                 redireciona para a página inicial
instalar.php              cria o primeiro usuário (apague depois de usar)
config/config.exemplo.php modelo das credenciais (copie para config.php)
banco/schema.sql          estrutura e dados iniciais
includes/
  funcoes.php             sessão, segurança, regras de agenda, mensagens
  cabecalho.php           topo e menu do painel
  rodape.php
publico/
  index.php               página inicial (serviços, preços, contato)
  agendar.php             formulário público
  horarios.php            devolve os horários livres (JSON)
admin/
  login.php  logout.php
  index.php               painel do dia
  agendamentos.php        lista com filtros e busca
  agendamento.php         aprovar / recusar / remarcar / avisar
  agendamento-novo.php    agendamento de balcão (já confirmado)
  clientes.php            clientes, pets e histórico
  servicos.php            serviços, duração e preço por porte
  configuracoes.php       regras da agenda, bloqueios, senha e equipe
assets/css, assets/js
```

## Segurança

- Senhas guardadas com `password_hash` (bcrypt, custo 12).
- Todas as consultas usam *prepared statements*.
- Todo formulário tem token CSRF.
- Toda saída passa por `htmlspecialchars`.
- Cookie de sessão `HttpOnly` e `SameSite=Lax`.

## O que ainda dá para crescer

- Envio automático da mensagem (hoje é um clique para abrir o WhatsApp).
- Relatório de faturamento por período.
- Lembrete automático na véspera do atendimento.

---

Desenvolvido por **M&M Tech**
