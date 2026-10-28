#!/bin/bash
set -e

echo "=== Instalação Otimizada do DaVinci Resolve para o Bamboo ==="

# 1. Checar espaço em disco (mínimo 12GB)
echo "[1/5] Verificando espaço disponível em disco..."
REQUIRED_SPACE_GB=12
REQUIRED_SPACE_KB=$((REQUIRED_SPACE_GB * 1024 * 1024))
HOME_AVAIL_KB=$(df "$HOME" | awk 'NR==2 {print $4}')
ROOT_AVAIL_KB=$(df / | awk 'NR==2 {print $4}')

if [ "$HOME_AVAIL_KB" -lt "$REQUIRED_SPACE_KB" ] || [ "$ROOT_AVAIL_KB" -lt "$REQUIRED_SPACE_KB" ]; then
    echo "Erro: Espaço em disco insuficiente. É necessário ao menos ${REQUIRED_SPACE_GB}GB livres em / e no diretório pessoal."
    exit 1
fi
echo "Espaço em disco suficiente verificado com sucesso."

# 2. Instalar dependências básicas de compilação e do sistema
echo "[2/5] Instalando ferramentas essenciais de compilação e dependências..."
pkexec pacman -S --needed --noconfirm base-devel git curl unzip tar fakeroot debugedit glu libxcrypt-compat

# 3. Detectar GPU e drivers OpenCL necessários para o DaVinci Resolve
echo "[3/5] Detectando GPU e configurando aceleração OpenCL..."
GPU_INFO=$(lspci -nnk 2>/dev/null | grep -iA3 'vga\|3d\|display' || true)

if echo "$GPU_INFO" | grep -iq "nvidia"; then
    echo "GPU NVIDIA detectada. Verificando drivers OpenCL da NVIDIA..."
    if ! pacman -Qq opencl-nvidia &>/dev/null; then
        echo "Instalando opencl-nvidia..."
        pkexec pacman -S --needed --noconfirm opencl-nvidia || true
    fi
elif echo "$GPU_INFO" | grep -iq "amd\|radeon\|advanced micro devices"; then
    echo "GPU AMD detectada. Instalando suporte OpenCL (ROCm / Rusticl)..."
    pkexec pacman -S --needed --noconfirm rocm-opencl-runtime ocl-icd clinfo 2>/dev/null || pkexec pacman -S --needed --noconfirm opencl-rusticl-mesa ocl-icd clinfo 2>/dev/null || true
    ACTUAL_USER="${SUDO_USER:-$USER}"
    if [ -n "$ACTUAL_USER" ] && [ "$ACTUAL_USER" != "root" ]; then
        pkexec usermod -aG render,video "$ACTUAL_USER" 2>/dev/null || true
    fi
elif echo "$GPU_INFO" | grep -iq "intel"; then
    echo "GPU Intel detectada. Instalando runtime OpenCL para Intel..."
    pkexec pacman -S --needed --noconfirm intel-compute-runtime ocl-icd clinfo 2>/dev/null || true
fi

# 4. Obter última versão oficial e baixar via API da Blackmagic Design
echo "[4/5] Obtendo informações da última versão estável do DaVinci Resolve..."
PRODUCT="DaVinci Resolve"
REFER_ID="dfd43085ef224766b06b579ce8a6d097"
SITE_URL="https://www.blackmagicdesign.com/api/support/latest-stable-version/davinci-resolve/linux"
USER_AGENT="Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"

RELEASE_INFO=$(curl -Ls "$SITE_URL")
PKG_VER=$(printf "%s" "$RELEASE_INFO" | awk -F'[,:]' '{for(i=1;i<=NF;i++){if($i~/"major"/){print $(i+1)} if($i~/"minor"/){print $(i+1)} if($i~/"releaseNum"/){print $(i+1)}}}' | sed 'N;s/\n/./;N;s/\n/./')
RELEASE_NUM=$(printf "%s" "$RELEASE_INFO" | awk -F'[,:]' '{for(i=1;i<=NF;i++){if($i~/"releaseNum"/){print $(i+1)}}}')

if [ "$RELEASE_NUM" == "0" ] || [ -z "$RELEASE_NUM" ]; then
    FILE_VER=$(printf "%s" "$RELEASE_INFO" | awk -F'[,:]' '{for(i=1;i<=NF;i++){if($i~/"major"/){print $(i+1)} if($i~/"minor"/){print $(i+1)}}}' | sed 'N;s/\n/./')
else
    FILE_VER="${PKG_VER}"
fi

DOWNLOAD_ID=$(printf "%s" "$RELEASE_INFO" | sed -n 's/.*"downloadId":"\([^"]*\).*/\1/p')

if [ -z "$DOWNLOAD_ID" ]; then
    echo "Erro: Não foi possível obter o ID de download da Blackmagic Design."
    exit 1
fi

ARCHIVE_NAME="DaVinci_Resolve_${FILE_VER}_Linux.zip"
echo "Versão detectada: $FILE_VER ($ARCHIVE_NAME)"

# Criar diretório de build
BUILD_DIR=$(mktemp -d -p "$HOME" .davinci_build_XXXXXX)
trap 'rm -rf "$BUILD_DIR"' EXIT
cd "$BUILD_DIR"

echo "Clonando pacote AUR do davinci-resolve..."
git clone "https://aur.archlinux.org/davinci-resolve.git" "$BUILD_DIR/pkg"
cd "$BUILD_DIR/pkg"

echo "Solicitando link de download seguro da Blackmagic Design..."
REQ_JSON="{\"firstname\": \"Bamboo\", \"lastname\": \"Linux\", \"email\": \"user@bamboolinux.org\", \"phone\": \"555-0199\", \"country\": \"us\", \"street\": \"Bamboo Way\", \"state\": \"California\", \"city\": \"San Jose\", \"product\": \"$PRODUCT\"}"

DOWNLOAD_ENDPOINT="https://www.blackmagicdesign.com/api/register/us/download/${DOWNLOAD_ID}"
SRC_URL=$(curl -s \
    -H 'Host: www.blackmagicdesign.com' \
    -H 'Accept: application/json, text/plain, */*' \
    -H 'Origin: https://www.blackmagicdesign.com' \
    -H "User-Agent: $USER_AGENT" \
    -H 'Content-Type: application/json;charset=UTF-8' \
    -H "Referer: https://www.blackmagicdesign.com/support/download/${REFER_ID}/Linux" \
    -H 'Accept-Encoding: gzip, deflate, br' \
    -H 'Accept-Language: en-US,en;q=0.9' \
    --data-ascii "$REQ_JSON" \
    --compressed \
    "$DOWNLOAD_ENDPOINT")

if [ -z "$SRC_URL" ] || ! echo "$SRC_URL" | grep -q "^http"; then
    echo "Erro: Falha ao obter URL de download da Blackmagic."
    exit 1
fi

echo "Baixando $ARCHIVE_NAME..."
curl -L -o "$ARCHIVE_NAME" "$SRC_URL"

# 5. Compilar e instalar via makepkg
echo "[5/5] Compilando e instalando pacote via makepkg..."
makepkg -si --noconfirm --needed

# 6. Patch de compatibilidade de bibliotecas (Linux Toys GLib Fix)
echo "Aplicando otimização de compatibilidade de bibliotecas..."
if [ -d /opt/resolve/libs ]; then
    pkexec bash -c 'mkdir -p /opt/resolve/libs/disabled && mv /opt/resolve/libs/libglib* /opt/resolve/libs/libgio* /opt/resolve/libs/libgmodule* /opt/resolve/libs/disabled/ 2>/dev/null || true'
fi

echo "========================================================"
echo "DaVinci Resolve instalado com sucesso no Bamboo OS!"
echo "Você pode iniciá-lo a partir do seu menu de aplicativos."
echo "========================================================"
