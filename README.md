# Smartec Gestão — PWA

PWA interno para unificar os módulos legados. O PMOC e o portal do cliente estão fora da primeira versão.

## Escopo da versão inicial

1. Login e perfis internos.
2. Clientes compartilhados.
3. Ordens de serviço: criação, status, itens, fotos e PDF.
4. Financeiro: despesas, custos e lançamentos relacionados a ordens.
5. Ferramentas: cadastro, fotos e relatórios.
6. API PHP + MySQL para hospedagem compartilhada da Hostinger.

## Estado atual

- PWA instalável, responsivo e com cache offline.
- Navegação unificada sem PMOC.
- Clientes e Ordens com fluxo local provisório em `localStorage`.
- Ordens com criação, visualização, edição, exclusão e filtro por status.
- Catálogo local de serviços com valor de referência.
- Financeiro: despesas, vencimento, status e totais locais.
- Ferramentas: patrimônio, código, status e observações locais.
- Ordens com valor total, foto do atendimento e impressão pelo navegador (inclusive "Salvar como PDF").
- Esquema inicial MySQL em `database/schema.sql`.
- API PHP protegida por sessão em `api/index.php`, pronta para Clientes, Serviços, Ordens, Financeiro e Ferramentas.
- Arquivos legados permanecem fora desta pasta, sem alterações.

## Como testar localmente

Abra `index.html` por um servidor local. Para testar recursos PWA, use um servidor HTTP e navegue para `http://127.0.0.1:4173`.

Fluxo atual: crie um cliente em **Clientes**, depois abra uma ordem em **Ordens de serviço**. O dashboard atualiza os contadores.

## Deploy estático para a Hostinger

No PowerShell do Windows onde a chave SSH já funciona, execute:

```powershell
Set-Location 'C:\Users\delis\Downloads\eder sistema\pwa-unificado'
.\deploy-hostinger.ps1
```

O script envia o PWA e a API para `sistema.gessosolution.com.br`; ele não altera o WordPress da raiz do domínio e nunca envia `api/config.php`.

## Configuração privada da API

Depois do deploy, execute `./configure-api-hostinger.ps1` no PowerShell. Ele pede a senha do MySQL somente no seu terminal, cria a configuração privada fora da pasta pública e gera uma chave para a criação única do primeiro administrador. Em seguida, execute `./create-first-admin.ps1` e informe essa chave. Não envie a senha ou a chave pelo chat.

## Próximas etapas de implementação

1. Evoluir Ordens para múltiplos itens e produtos, caso a operação exija essa granularidade.
2. Adicionar edição e filtros avançados aos módulos Financeiro e Ferramentas.
4. Ligar as telas do PWA à API autenticada e concluir a tela de login.
5. Migrar os backups JSON antigos para o banco, após validação humana.
6. Publicar em HTTPS e trocar o armazenamento local pela API.

## Transição para Codex Cloud

O Cloud pode continuar a partir desta pasta. Ele deve tratar `database/schema.sql` como base inicial, não como banco já publicado. Não há senha, token, certificado ou credencial do servidor dentro de `pwa-unificado`.

Para publicar, será necessário criar um banco MySQL e fornecer as credenciais diretamente no ambiente de deploy, nunca no repositório ou no chat. A versão offline foi validada nas rotas Clientes, Serviços, Ordens de serviço, Financeiro e Ferramentas sem erros de JavaScript.
