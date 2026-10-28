#!/bin/bash
set -e

echo "=== Instalação do Linux Toys para o Bamboo ==="
echo "Preparando instalação via AUR (paru/yay)..."

if command -v paru &>/dev/null; then
    echo "Instalando linuxtoys-bin via paru..."
    paru -S --needed linuxtoys-bin
elif command -v yay &>/dev/null; then
    echo "Instalando linuxtoys-bin via yay..."
    yay -S --needed linuxtoys-bin
else
    echo "Erro: Nenhum AUR helper (paru ou yay) encontrado para instalar o linuxtoys-bin."
    exit 1
fi

echo "========================================================"
echo "Linux Toys instalado com sucesso!"
echo "Você pode iniciá-lo pelo menu de aplicativos ou executando 'linuxtoys'."
echo "========================================================"
