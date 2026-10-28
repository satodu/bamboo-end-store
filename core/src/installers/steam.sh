#!/bin/bash
set -e

echo "=== Instalação Otimizada da Steam para o Bamboo ==="

# 1. Habilitar multilib no /etc/pacman.conf se necessário
echo "[1/3] Verificando repositório [multilib]..."
if ! pacman -Slq multilib &>/dev/null; then
    echo "Repositório [multilib] não está ativo. Habilitando..."
    if grep -q "^#\[multilib\]" /etc/pacman.conf; then
        pkexec sed -i '/^#\[multilib\]/{s/^#//;n;s/^#//}' /etc/pacman.conf
    else
        printf "\n[multilib]\nInclude = /etc/pacman.d/mirrorlist\n" | pkexec tee -a /etc/pacman.conf >/dev/null
    fi
    echo "Atualizando listas de pacotes..."
    pkexec pacman -Sy
else
    echo "Repositório [multilib] já está habilitado."
fi

# 2. Instalar Steam e suporte a periféricos / 32-bit
echo "[2/3] Instalando Steam e regras de periféricos (steam-devices)..."
pkexec pacman -S --needed --noconfirm steam steam-devices lib32-alsa-plugins

# 3. Detectar GPU e instalar bibliotecas Vulkan / Mesa 32-bit
echo "[3/3] Detectando hardware gráfico para aceleração 32-bit..."
GPU_INFO=$(lspci -nnk 2>/dev/null | grep -iA3 'vga\|3d\|display' || true)

EXTRA_PKGS=""

if echo "$GPU_INFO" | grep -iq "amd\|radeon\|advanced micro devices"; then
    echo "GPU AMD detectada. Selecionando bibliotecas 32-bit e Vulkan para AMD..."
    EXTRA_PKGS="$EXTRA_PKGS vulkan-radeon lib32-vulkan-radeon lib32-mesa"
fi

if echo "$GPU_INFO" | grep -iq "intel"; then
    echo "GPU Intel detectada. Selecionando bibliotecas 32-bit e Vulkan para Intel..."
    EXTRA_PKGS="$EXTRA_PKGS vulkan-intel lib32-vulkan-intel lib32-mesa"
fi

if echo "$GPU_INFO" | grep -iq "nvidia"; then
    echo "GPU NVIDIA detectada. Verificando drivers NVIDIA..."
    if pacman -Qq nvidia &>/dev/null || pacman -Qq nvidia-open &>/dev/null || pacman -Qq nvidia-dkms &>/dev/null; then
        EXTRA_PKGS="$EXTRA_PKGS lib32-nvidia-utils"
    fi
fi

if [ -n "$EXTRA_PKGS" ]; then
    echo "Instalando pacotes gráficos adicionais: $EXTRA_PKGS"
    pkexec pacman -S --needed --noconfirm $EXTRA_PKGS || echo "Aviso: Alguns pacotes complementares já estão configurados ou são fornecidos por outro driver."
fi

echo "========================================================"
echo "Instalação da Steam (Otimizada para Bamboo) concluída com sucesso!"
echo "========================================================"
