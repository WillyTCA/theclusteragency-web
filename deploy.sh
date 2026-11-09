#!/usr/bin/env bash
# Sube la web nueva por SFTP a https://theclusteragency.com/nueva/ (carpeta aparte; WordPress no se toca).
# Mismo método que el deploy.sh de la suite: clave SSH, subida a temporal, hash SHA-256 verificado,
# copia previa en el servidor FUERA de public_html y paso final atómico por archivo.
#
#   ./deploy.sh              build + sube lo que haya cambiado (incremental; FORZAR=1 sube todo)
#   ./deploy.sh volver       deshace el ÚLTIMO despliegue
#   ./deploy.sh usuario NOMBRE   crea o cambia la contraseña de un usuario del panel del blog (pide la clave por teclado)
#   ./deploy.sh comprobar    solo comprueba lo publicado
#
# NUNCA sube admin/config.php (el del servidor manda) y NUNCA borra nada de uploads/ ni de ~/web-data/.
# No despliegues sin que Willy lo diga.
#
# Variables (~/.zshrc): TCA_SSH_USER (obligatoria); TCA_SSH_HOST (ftp.theclusteragency.com);
# TCA_SSH_KEY (~/.ssh/tca_deploy); TCA_WEB_PATH (public_html/nueva); TCA_WEB_URL (https://theclusteragency.com/nueva)
# Estado: la lógica se probó con PHP en local; la subida SFTP real está SIN PROBAR contra el servidor.
set -e
SSH_USER="${TCA_SSH_USER:?Falta TCA_SSH_USER (mira la cabecera de este archivo)}"
SSH_HOST="${TCA_SSH_HOST:-ftp.theclusteragency.com}"
SSH_KEY="${TCA_SSH_KEY:-$HOME/.ssh/tca_deploy}"
BASE="${TCA_WEB_PATH:-public_html/nueva}"
URL="${TCA_WEB_URL:-https://theclusteragency.com/nueva}"
QUE="${1:-subir}"
PREV_RAIZ="deploy-prev-web"; PREV="$PREV_RAIZ/$(date +%Y%m%d-%H%M%S)"; CONSERVAR=5
[ -f "$SSH_KEY" ] || { echo "No existe la clave $SSH_KEY"; exit 1; }
SSHO=(-i "$SSH_KEY" -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=15 -q)
DEST="$SSH_USER@$SSH_HOST"
cd "$(dirname "$0")"
sha () { if command -v shasum >/dev/null; then shasum -a 256 "$1" | cut -d' ' -f1; else sha256sum "$1" | cut -d' ' -f1; fi; }
FALLOS=0; SUBIDOS=0; IGUALES=0

subir () {   # subir <local> <remoto relativo al home>
  local lf="$1" re="$2" tmp="$2.tmp-deploy" esp real err
  esp=$(sha "$lf")
  if [ "${FORZAR:-0}" != "1" ]; then
    real=$(ssh -n "${SSHO[@]}" "$DEST" "sha256sum '$re' 2>/dev/null" 2>/dev/null | cut -d' ' -f1)
    [ "$real" = "$esp" ] && { IGUALES=$((IGUALES+1)); return 0; }
  fi
  printf '  %-46s' "${re#"$BASE"/}"
  if ! err=$(printf 'put "%s" "%s"\n' "$lf" "$tmp" | sftp "${SSHO[@]}" -b - "$DEST" 2>&1); then
    echo "FALLÓ al subir ($(echo "$err" | head -c 100 | tr '\n' ' '))"; ssh "${SSHO[@]}" "$DEST" "rm -f '$tmp'" >/dev/null 2>&1 || true; FALLOS=$((FALLOS+1)); return 0
  fi
  real=$(ssh -n "${SSHO[@]}" "$DEST" "sha256sum '$tmp'" 2>/dev/null | cut -d' ' -f1)
  if [ "$real" != "$esp" ]; then echo "FALLÓ: hash remoto distinto; no se tocó el real"; ssh "${SSHO[@]}" "$DEST" "rm -f '$tmp'" >/dev/null 2>&1 || true; FALLOS=$((FALLOS+1)); return 0; fi
  if ! ssh -n "${SSHO[@]}" "$DEST" "set -e; mkdir -p '$PREV/$(dirname "$re")';
      if [ -f '$re' ]; then cp -p '$re' '$PREV/$re'; else echo '$re' >> '$PREV/_nuevos.txt'; fi; mv -f '$tmp' '$re'" >/dev/null 2>&1; then
    echo "FALLÓ al guardar copia previa o mover; no se tocó el real"; ssh "${SSHO[@]}" "$DEST" "rm -f '$tmp'" >/dev/null 2>&1 || true; FALLOS=$((FALLOS+1)); return 0
  fi
  SUBIDOS=$((SUBIDOS+1)); echo "ok (hash verificado)"
}

comprobar () {
  echo; echo "Comprobando $URL …"; local mal=0 c
  c=$(curl -s -m 30 -o /dev/null -w '%{http_code}' "$URL/");                      [ "$c" = 200 ] && echo "  ✓ la portada responde 200" || { echo "  ✗ portada: $c"; mal=1; }
  c=$(curl -s -m 30 -o /dev/null -w '%{http_code}' "$URL/plantilla-blog/");       [ "$c" = 200 ] && echo "  ✓ plantilla del blog presente" || { echo "  ✗ plantilla-blog: $c"; mal=1; }
  c=$(curl -s -m 30 -o /dev/null -w '%{http_code}' "$URL/admin/");                [ "$c" = 200 ] && echo "  ✓ el panel pide login (200)" || { echo "  ✗ admin: $c"; mal=1; }
  c=$(curl -s -m 30 -o /dev/null -w '%{http_code}' "$URL/admin/api.php?op=listar"); [ "$c" = 401 ] && echo "  ✓ la API NO responde sin sesión" || { echo "  ✗ api sin sesión responde $c (debía ser 401)"; mal=1; }
  for f in _lib.php config.php; do c=$(curl -s -m 30 -o /dev/null -w '%{http_code}' "$URL/admin/$f"); { [ "$c" = 403 ] || [ "$c" = 404 ]; } && echo "  ✓ admin/$f no se sirve ($c)" || { echo "  ✗ admin/$f responde $c"; mal=1; }; done
  c=$(curl -s -m 30 -I "$URL/" | tr -d '\r' | grep -ci '^x-robots-tag:.*noindex'); [ "$c" -ge 1 ] && echo "  ✓ X-Robots-Tag noindex activo" || { echo "  ✗ falta X-Robots-Tag noindex"; mal=1; }
  local v; v=$(grep -o "VERSION = '[^']*'" src/config.ts | cut -d"'" -f2)
  curl -s -m 30 "$URL/" | grep -q "v$v" && echo "  ✓ el pie muestra la versión $v" || { echo "  ✗ el pie NO muestra la versión $v"; mal=1; }
  c=$(curl -s -m 30 -o /dev/null -w '%{http_code}' "https://theclusteragency.com/"); [ "$c" = 200 ] && echo "  ✓ WordPress (raíz) sigue respondiendo 200" || { echo "  ✗ la raíz responde $c"; mal=1; }
  return $mal
}

case "$QUE" in
volver)
  ultimo=$(ssh -n "${SSHO[@]}" "$DEST" "ls -1d $PREV_RAIZ/*/ 2>/dev/null | sort | tail -1" | tr -d '\r'); ultimo="${ultimo%/}"
  [ -n "$ultimo" ] || { echo "No hay copias previas ($PREV_RAIZ/)."; exit 1; }
  echo "Último despliegue guardado: $ultimo"; ssh -n "${SSHO[@]}" "$DEST" "cd '$ultimo' && find . -type f ! -name _nuevos.txt | sed 's|^\./|  restaurar |'"
  ssh -n "${SSHO[@]}" "$DEST" "[ -s '$ultimo/_nuevos.txt' ] && sed 's|^|  borrar (era nuevo) |' '$ultimo/_nuevos.txt'" || true
  read -r -p "¿Seguro? Escribe 'si': " r; [ "$r" = "si" ] || { echo "Cancelado."; exit 0; }
  ssh -n "${SSHO[@]}" "$DEST" "set -e; cd '$ultimo'; find . -type f ! -name _nuevos.txt | sed 's|^\./||' | while read -r f; do cp -p \"\$f\" \"\$HOME/\$f.tmp-volver\" && mv -f \"\$HOME/\$f.tmp-volver\" \"\$HOME/\$f\" && echo \"  restaurado \$f\"; done; if [ -s _nuevos.txt ]; then while read -r f; do rm -f \"\$HOME/\$f\" && echo \"  borrado \$f\"; done < _nuevos.txt; fi"
  echo "Hecho."; exit 0;;
usuario)
  nom="${2:?Uso: ./deploy.sh usuario NOMBRE}"; [[ "$nom" =~ ^[A-Za-z0-9_.-]{2,32}$ ]] || { echo "Nombre no válido (2-32: letras, números . _ -)"; exit 1; }
  read -r -s -p "Contraseña para $nom (mín. 10 caracteres): " p1; echo; read -r -s -p "Repítela: " p2; echo
  [ "$p1" = "$p2" ] && [ ${#p1} -ge 10 ] || { echo "No coinciden o son menos de 10 caracteres."; exit 1; }
  CODE='[$u,$p]=explode("\n",stream_get_contents(STDIN),2);$d=getenv("HOME")."/web-data";@mkdir($d,0700,true);$f="$d/usuarios.json";$j=json_decode((string)@file_get_contents($f),true)?:[];$j[$u]=["hash"=>password_hash(rtrim($p,"\n"),PASSWORD_DEFAULT),"rol"=>"admin"];file_put_contents($f,json_encode($j,JSON_PRETTY_PRINT));chmod($f,0600);echo "ok\n";'
  printf '%s\n%s\n' "$nom" "$p1" | ssh "${SSHO[@]}" "$DEST" "php -r '$CODE'" && echo "Usuario $nom listo (guardado fuera de la web, en ~/web-data/usuarios.json)."; exit 0;;
comprobar) comprobar; exit $?;;
subir) ;;
*) echo "Uso: ./deploy.sh [subir|volver|usuario NOMBRE|comprobar]"; exit 1;;
esac

echo "== build =="; npm run build >/dev/null 2>&1 || { npm run build; exit 1; }
[ -f dist/plantilla-blog/index.html ] && [ -f dist/admin/index.php ] || { echo "Falta algo en dist/ (plantilla-blog o admin)"; exit 1; }
echo "== carpetas =="; ssh "${SSHO[@]}" "$DEST" "mkdir -p $(cd dist && find . -type d | sed "s|^\.|$BASE|" | tr '\n' ' ')"
echo "== subida a $BASE =="
while IFS= read -r f; do
  rel="${f#./}"
  case "$rel" in admin/config.php) echo "  (se salta admin/config.php: manda el del servidor)"; continue;; esac
  subir "dist/$rel" "$BASE/$rel"
done < <(cd dist && find . -type f | sort)
ssh -n "${SSHO[@]}" "$DEST" "cd $PREV_RAIZ 2>/dev/null && ls -1d */ | sort | head -n -$CONSERVAR | xargs -r rm -rf" >/dev/null 2>&1 || true
echo; echo "Subidos: $SUBIDOS · iguales (saltados): $IGUALES · fallos: $FALLOS"
[ "$FALLOS" = 0 ] || { echo "✗ Hubo fallos. Lo que falló no se tocó en producción."; exit 1; }
comprobar || { echo "✗ Alguna comprobación falló: mira arriba. ./deploy.sh volver deshace."; exit 1; }
echo "✓ Desplegado y comprobado."
