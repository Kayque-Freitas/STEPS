# Sistema de Tutoriais e POP's - Versão 2.0 (Revisada)

Uma aplicação web robusta e segura para gerenciar tutoriais em vídeo e Procedimentos Operacionais Padrão (POPs), com foco em segurança, arquitetura modular e experiência do usuário.

## 🚀 Visão Geral das Melhorias

A versão 2.0 representa uma **revisão completa** do projeto original, focada em transformar a aplicação em uma solução mais segura, escalável e fácil de manter.

| Área | Versão Original | Versão 2.0 (Revisada) |
| :--- | :--- | :--- |
| **Arquitetura** | Procedural, lógica e apresentação misturadas | Arquitetura MVC (parcial), separação de responsabilidades |
| **Persistência** | Sem banco de dados, gestão de categorias e vídeos via sistema de arquivos | **Banco de Dados SQLite** para metadados e autenticação |
| **Segurança** | Credenciais hardcoded, sem hash de senha, sem proteção CSRF | **Hash de Senha (Bcrypt)**, **Proteção CSRF**, Logs de Auditoria |
| **QR Code** | Dependência de API externa | **Geração Local** de QR Codes |
| **Interface** | Bootstrap básico | Bootstrap 5 com melhorias de UI/UX e Dashboard Administrativo |
| **Upload** | Lógica de upload simples | Validação de arquivos (tamanho/tipo) e upload assíncrono (AJAX) |

## ✨ Funcionalidades

*   **Autenticação Segura**: Login com hash de senha (Bcrypt) e timeout de sessão.
*   **Gerenciamento de Categorias**: CRUD completo de categorias via banco de dados.
*   **Gerenciamento de Vídeos**: Upload, visualização, e exclusão de vídeos com metadados no DB.
*   **Geração de QR Codes**: Geração local e instantânea para compartilhamento.
*   **Dashboard Administrativo**: Visão geral e logs de auditoria.
*   **Visualização Pública**: Página dedicada para assistir vídeos com sugestões relacionadas.

## 🛠️ Requisitos

*   PHP 7.4+
*   Extensões PHP: `pdo_sqlite`, `gd`, `fileinfo`
*   Servidor web (Apache/Nginx) com suporte a `.htaccess` e `mod_rewrite`

## ⚙️ Instalação

1.  **Clone o repositório:**
    ```bash
    git clone https://github.com/Kayque-Freitas/Sistema-de-Videos-POP-s-e-Tutoriais-De-Expedi-o.git
    cd Sistema-de-Videos-POP-s-e-Tutoriais-De-Expedi-o
    ```

2.  **Configuração de Diretórios:**
    O script `config.php` irá criar automaticamente os diretórios necessários: `uploads/`, `thumbs/`, `data/`, e `qrcodes/`. Certifique-se de que o servidor web tem permissão de escrita nesses diretórios.

3.  **Acesso Inicial:**
    *   Acesse a aplicação no seu navegador.
    *   O banco de dados SQLite (`data/database.db`) será criado automaticamente.
    *   Use as credenciais padrão para o primeiro acesso:
        *   **Usuário**: `admin`
        *   **Senha**: `admin123`

4.  **Segurança Pós-Instalação:**
    **É crucial** que você altere a senha do usuário `admin` imediatamente após o primeiro login.

## 📂 Estrutura do Projeto

```
.
├── api/
│   ├── get_videos.php       # Endpoint para listar vídeos (AJAX)
│   ├── upload_video.php     # Endpoint para upload de vídeo (AJAX)
│   └── generate_qr.php      # Endpoint para gerar QR Code (Local)
├── data/
│   └── database.db          # Banco de dados SQLite
├── lib/
│   └── qrcode.php           # Biblioteca de QR Code (Simplificada)
├── uploads/                 # Vídeos (organizados por ID de categoria)
├── thumbs/                  # Thumbnails
├── .htaccess                # Regras de segurança e reescrita
├── config.php               # Configurações e funções utilitárias (DB, Segurança)
├── index.php                # Dashboard Administrativo (Principal)
├── login.php                # Página de Login Segura
├── logout.php               # Logout
└── watch.php                # Página de Visualização Pública do Vídeo
```

## 🛡️ Notas de Segurança

*   **Não use as credenciais padrão em produção.** Altere a senha imediatamente.
*   **Use HTTPS** em produção para proteger a transmissão de dados.
*   O arquivo `.htaccess` foi adicionado para proteger arquivos sensíveis como o banco de dados (`.db`) e o arquivo de configuração.
*   A aplicação agora registra logs de auditoria para monitorar ações importantes.

---
*Revisado por Manus AI*
