#!/usr/bin/env bash
# Despliegue por SFTP de la web nueva. NO toca WordPress ni la suite.
# Uso: ./deploy.sh            -> build + sube a la URL de pruebas
#      ./deploy.sh volver     -> deshace el último despliegue
# Requiere en el entorno: TCA_SSH_USER, TCA_SSH_HOST, TCA_WEB_DIR (carpeta del subdominio,
# p. ej. public_html/nueva) y la clave ~/.ssh/tca_deploy. Estado: SIN PROBAR contra el servidor.
set -euo pipefail
: "${TCA_SSH_USER:?falta TCA_SSH_USER}" "${TCA_SSH_HOST:?falta TCA_SSH_HOST}" "${TCA_WEB_DIR:?falta TCA_WEB_DIR}"
KEY="${TCA_SSH_KEY:-$HOME/.ssh/tca_deploy}"
SSH=(ssh -i "$KEY" -o BatchMode=yes "$TCA_SSH_USER@$TCA_SSH_HOST")
PREV="deploy-prev-web"   # en ~ del servidor, fuera de public_html

if [[ "${1:-}" == "volver" ]]; then
  "${SSH[@]}" "set -e; [ -d $PREV/last ] || { echo 'No hay copia previa'; exit 1; }; rm -rf $TCA_WEB_DIR.tmp; cp -a $PREV/last $TCA_WEB_DIR.tmp; rm -rf $TCA_WEB_DIR; mv $TCA_WEB_DIR.tmp $TCA_WEB_DIR"
  echo "Vuelto al despliegue anterior."; exit 0
fi

npm run build
LOCAL_HASH=$(cd dist && find . -type f -print0 | sort -z | xargs -0 sha256sum | sha256sum | cut -d' ' -f1)
TMP="$TCA_WEB_DIR.tmp"
"${SSH[@]}" "rm -rf $TMP && mkdir -p $TMP"
sftp -i "$KEY" -o BatchMode=yes -r "$TCA_SSH_USER@$TCA_SSH_HOST" <<EOF
put -r dist/* $TMP/
put -r dist/.htaccess $TMP/.htaccess
EOF
REMOTE_HASH=$("${SSH[@]}" "cd $TMP && find . -type f -print0 | sort -z | xargs -0 sha256sum | sha256sum | cut -d' ' -f1")
[[ "$LOCAL_HASH" == "$REMOTE_HASH" ]] || { echo "✗ El hash no coincide; no se publica."; exit 1; }
"${SSH[@]}" "set -e; mkdir -p $PREV; rm -rf $PREV/last; [ -d $TCA_WEB_DIR ] && cp -a $TCA_WEB_DIR $PREV/last || true; rm -rf $TCA_WEB_DIR; mv $TMP $TCA_WEB_DIR"
echo "✓ Subido y verificado (hash $LOCAL_HASH). Comprueba la versión en el pie de la web."
