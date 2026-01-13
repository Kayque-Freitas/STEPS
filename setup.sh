#!/bin/bash

# Script de Configuração STEPS - Neobrutalism Edition
# Configura PHP e SQLite para desenvolvimento local

echo "🚀 Iniciando configuração do ambiente STEPS..."

# 1. Atualizar pacotes e instalar PHP + extensões
echo "📦 Instalando PHP e dependências..."
sudo apt-get update
sudo apt-get install -y php-cli php-mbstring php-xml php-sqlite3 curl

# 2. Criar diretórios necessários
echo "📁 Criando estrutura de pastas..."
mkdir -p uploads thumbs data qrcodes
chmod -R 777 uploads thumbs data qrcodes

# 3. Verificar instalação
PHP_VER=$(php -v | head -n 1)
echo "✅ $PHP_VER instalado com sucesso!"

# 4. Instruções de execução
IP_LOCAL=$(hostname -I | awk '{print $1}')
echo ""
echo "--------------------------------------------------"
echo "✨ Ambiente configurado!"
echo "Para iniciar o servidor, execute:"
echo "php -S 0.0.0.0:8080"
echo ""
echo "Acesse em seu navegador:"
echo "http://localhost:8080 ou http://$IP_LOCAL:8080"
echo "--------------------------------------------------"
echo "Credenciais padrão: admin / admin123"
