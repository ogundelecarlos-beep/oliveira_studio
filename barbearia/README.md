# Navalha & Co. — Sistema web para barbearia

Sistema completo em **PHP + MySQL** com site público, agendamento online e painel administrativo.
Todos os nomes, telefones, e-mails e endereços são **fictícios** (apenas para demonstração).

---

## 1. Requisitos

| Item | Versão |
|---|---|
| PHP | 7.4 ou superior (recomendado 8.1+) |
| Extensões PHP | `pdo_mysql`, `mbstring`, `fileinfo`, `json` (padrão no XAMPP/Laragon/WAMP) |
| Banco | MySQL 5.7+ / 8.x ou MariaDB 10.3+ |
| Servidor | Apache (XAMPP, WAMP, Laragon, hospedagem compartilhada) ou o servidor embutido do PHP |

---

## 2. Instalação rápida (XAMPP / WAMP / Laragon)

1. **Copie a pasta** `barbearia/` para o diretório do servidor:
   - XAMPP: `C:\xampp\htdocs\barbearia`
   - Laragon: `C:\laragon\www\barbearia`
2. **Crie o banco e importe os dados.** Escolha uma das opções:
   - **phpMyAdmin:** abra `http://localhost/phpmyadmin` → aba **Importar** → selecione `database/barbearia.sql` → **Executar**.
     O script já cria o banco `barbearia`.
   - **Terminal:**
     ```bash
     mysql -u root -p < database/barbearia.sql
     ```
3. **Configure o acesso ao banco** (se o seu usuário/senha não for `root` sem senha):
   ```bash
   cp config/config.local.example.php config/config.local.php
   ```
   Edite `config/config.local.php` com `DB_HOST`, `DB_NAME`, `DB_USER` e `DB_PASS`.
4. **Permissão de escrita** na pasta `uploads/` (no Linux: `chmod 775 uploads`).
5. Acesse:
   - Site: `http://localhost/barbearia/`
   - Painel: `http://localhost/barbearia/admin/`

> A URL base é detectada automaticamente, então o sistema funciona na raiz do domínio ou em subpastas.

### Alternativa: servidor embutido do PHP

```bash
cd barbearia
php -S localhost:8000 router.php
```
Acesse `http://localhost:8000`. O `router.php` replica as proteções dos arquivos `.htaccess`.

---

## 3. Acessos de demonstração

| Perfil | E-mail | Senha |
|---|---|---|
| Administrador | `admin@barbearia.com` | `admin123` |
| Funcionário (recepção) | `recepcao@barbearia.com` | `barber123` |

**Troque as senhas após o primeiro acesso** (menu do usuário → *Meu perfil*).
Funcionários não acessam *Usuários* nem *Configurações*.

---

## 4. Funcionalidades

### Site público
- **Home:** hero, serviços em destaque, sobre, barbeiros, promoção vigente, galeria com filtros, avaliações aprovadas, localização com mapa e botão *Como chegar*, rodapé e botão flutuante do WhatsApp.
- **Páginas:** `servicos.php`, `barbeiros.php`, `galeria.php` (filtros + lightbox), `contato.php` (formulário salvo no banco).
- **Agendamento (`agendamento.php`)** em 6 etapas: serviço → barbeiro (ou *qualquer profissional disponível*) → data → horários livres → dados do cliente → resumo e confirmação.
  Depois de confirmar, o cliente recebe o protocolo, o botão **Confirmar pelo WhatsApp** (mensagem gerada automaticamente) e um link para **cancelar** o agendamento.
- **Avaliações:** clientes com atendimento concluído podem avaliar; a avaliação só aparece no site depois de aprovada.

### Painel (`/admin`)
- **Login** com proteção contra força bruta (5 tentativas a cada 15 min por IP) e **recuperação de senha** por token de uso único (1 hora).
- **Dashboard:** agendamentos de hoje, clientes, faturamento do mês, serviços realizados, próximos agendamentos (com confirmar, concluir e lembrete por WhatsApp), ranking de serviços e faturamento por barbeiro.
- **Agenda:** visões **Dia / Semana / Mês**, com filtro por barbeiro. Permite criar (clicando no horário livre), editar, confirmar, concluir, cancelar, trocar barbeiro, trocar horário e **bloquear horários**. Mostra quando cada barbeiro não está trabalhando.
- **Clientes:** busca, ordenação e ficha com dados pessoais, histórico, serviços realizados, datas e valor gasto.
- **Barbeiros, Serviços, Promoções e Galeria:** cadastro completo (criar, editar, excluir, ativar/desativar, upload de imagem).
- **Horários:** funcionamento por dia da semana (com intervalo opcional), jornada própria por barbeiro e bloqueios (feriado, folga, manutenção, compromisso).
- **Avaliações:** aprovar, ocultar e excluir. **Mensagens** do formulário de contato. **Usuários** do painel. **Configurações** da barbearia (nome, logo, endereço, telefone, WhatsApp, redes sociais, Google Maps e regras de agendamento).

---

## 5. Regras de negócio implementadas

1. **Sem dois agendamentos no mesmo horário para o mesmo barbeiro:** a sobreposição de intervalos é verificada no servidor, dentro de uma transação com trava (`SELECT … FOR UPDATE`), e um **índice único** no banco (`barbeiro_id, data, hora, slot_ativo`) serve de garantia final.
2. Horários ocupados aparecem **desabilitados**, e horários já passados (ou dentro da antecedência mínima) não são oferecidos.
3. Não é possível agendar **fora do expediente** da barbearia ou do barbeiro.
4. Não é possível agendar em **horários bloqueados**, e o painel não deixa criar um bloqueio por cima de agendamentos ativos.
5. **Cancelamento** pelo cliente (link com token) e pelo painel. Cancelar libera o horário na hora.
6. A disponibilidade é calculada **em tempo real** a cada consulta, sem cache.
7. **Telefone** (DDD + 8/9 dígitos) e **e-mail** são validados no navegador e no servidor.
8. Painel protegido por sessão (cookie `HttpOnly` e `SameSite`, ID regenerado no login, expiração após 2 h sem uso).
9. Senhas guardadas com `password_hash()` (bcrypt) e rehash automático.
10. **Todas** as consultas usam *prepared statements* do PDO (emulação desativada).
11. Todos os formulários são validados no servidor e têm proteção **CSRF**.
12. Mensagens de erro e de sucesso claras (alertas em português).
13. Uploads conferem o tipo real do arquivo (`finfo` + `getimagesize`), limitam o tamanho a 5 MB e usam nomes aleatórios; a pasta `uploads/` não executa scripts.
14. As saídas são escapadas (`htmlspecialchars`) contra XSS, e os cabeçalhos de segurança são enviados (`X-Frame-Options`, `nosniff`, `Referrer-Policy`).

---

## 6. Banco de dados

O arquivo `database/barbearia.sql` cria **todas as tabelas pedidas** (`usuarios`, `clientes`, `barbeiros`, `servicos`, `agendamentos`, `horarios`, `bloqueios`, `avaliacoes`, `promocoes`, `galeria`, `configuracoes`), com chaves primárias, estrangeiras e índices, e carrega os dados de demonstração.

Relacionamentos: `clientes 1:N agendamentos`, `barbeiros 1:N agendamentos`, `servicos 1:N agendamentos`, `barbeiros 1:N horarios`, `barbeiros 1:N bloqueios`, `clientes 1:N avaliacoes` (e `agendamentos 1:N avaliacoes`).

Colunas e tabelas extras, criadas para o sistema funcionar por completo:
- `agendamentos.hora_fim` e `agendamentos.valor`: guardam a duração e o preço do momento do agendamento, para que o histórico não mude quando o preço for alterado. `token` é usado no link de cancelamento.
- `horarios.barbeiro_id = NULL`: horário de funcionamento da barbearia. `bloqueios.barbeiro_id = NULL`: bloqueio geral. `hora_inicio` nulo significa o dia inteiro.
- `promocoes.servico_id` e `promocoes.imagem`: o preço promocional é aplicado automaticamente ao serviço vinculado.
- `avaliacoes.aprovado`: 0 = pendente, 1 = aprovada, 2 = oculta.
- Tabelas `mensagens` (contato), `recuperacao_senha` e `login_tentativas`.

As datas dos agendamentos de exemplo são relativas ao dia da importação, então o dashboard sempre tem dados de "hoje". Para voltar ao estado inicial, basta importar o `.sql` novamente.

---

## 7. Estrutura de pastas

```
barbearia/
├── index.php  servicos.php  barbeiros.php  galeria.php  contato.php
├── agendamento.php          # fluxo de agendamento em 6 etapas
├── cancelar.php             # cancelamento pelo cliente (link com token)
├── acoes/avaliacao.php      # recebe avaliações do site
├── api/
│   ├── disponibilidade.php  # horários livres (JSON)
│   └── agendar.php          # cria o agendamento (JSON)
├── admin/
│   ├── login.php  logout.php  recuperar-senha.php  redefinir-senha.php  perfil.php
│   ├── dashboard.php  agenda.php  clientes.php  barbeiros.php  servicos.php
│   ├── horarios.php  promocoes.php  avaliacoes.php  galeria.php  mensagens.php
│   ├── usuarios.php  configuracoes.php
│   └── includes/ (auth.php, layout.php)
├── config/      # config.php, database.php, config.local.example.php
├── includes/    # bootstrap, funções, regras de disponibilidade, layout do site, ícones
├── css/         # style.css (site), admin.css (painel), fonts.css
├── js/          # script.js, agendamento.js, admin.js, admin-agenda.js
├── fonts/       # Big Shoulders Display + Manrope (auto-hospedadas, licença OFL)
├── img/         # logo, favicon e imagens alternativas
├── uploads/     # imagens enviadas pelo painel
└── database/barbearia.sql
```

---

## 8. Personalização e produção

- **Fotos:** as imagens de demonstração vêm do Unsplash (licença gratuita) e são carregadas por URL. Se alguma não carregar, o site mostra uma imagem alternativa no mesmo estilo. Para usar fotos próprias, envie-as pelo painel (Barbeiros, Serviços, Promoções e Galeria). As fotos do topo das páginas ficam em `index.php` e nas variáveis `$heroImage` de cada página.
- **Identidade:** as cores ficam em variáveis no topo de `css/style.css` (`--gold`, `--bg`, …).
- **E-mail de recuperação de senha:** usa a função `mail()` do PHP. Configure o SMTP do servidor. Enquanto `APP_DEBUG` for `true` e não houver servidor de e-mail, o link aparece na própria tela.
- **Antes de publicar:**
  - defina `APP_DEBUG` como `false` e `APP_URL` com o seu domínio em `config/config.local.php`;
  - use HTTPS (o cookie de sessão passa a ser `Secure` automaticamente);
  - troque as senhas de demonstração e os dados em *Configurações*;
  - confirme que o Apache respeita os `.htaccess` (`AllowOverride All`), que bloqueiam `config/`, `includes/` e `database/`.
