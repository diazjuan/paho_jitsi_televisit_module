# Solución de Problemas HTTPS para PAHO Jitsi Televisit

## Problema: Contenido Mixto HTTP/HTTPS

Cuando OpenEMR está en HTTPS pero Jitsi intenta cargar recursos HTTP, el navegador bloquea el contenido por seguridad (mixed content).

## Solución Implementada

El módulo ahora **automáticamente abre Jitsi en una ventana separada** cuando detecta que OpenEMR está en HTTPS. Esto evita problemas de contenido mixto y asegura que todas las funciones de video funcionen correctamente.

## Verificar Configuración de Jitsi

### 1. Verificar que Jitsi esté configurado para HTTPS

Asegúrate de que tu instancia de Jitsi en `https://169.61.173.4:8440` esté configurada correctamente:

```bash
# Verificar que Jitsi esté escuchando en HTTPS
curl -k https://169.61.173.4:8440
```

### 2. Verificar configuración de Jitsi Meet

Si estás usando Jitsi Meet, verifica que el archivo de configuración (`/etc/jitsi/meet/your-domain-config.js`) tenga:

```javascript
var config = {
    // ... otras configuraciones
    useStunTurn: true,
    // Asegurar que todas las URLs sean HTTPS
    bosh: 'https://169.61.173.4:8440/http-bind',
    // ...
};
```

### 3. Verificar certificado SSL

Asegúrate de que Jitsi tenga un certificado SSL válido:

```bash
# Verificar certificado
openssl s_client -connect 169.61.173.4:8440 -showcerts
```

### 4. Configuración de Nginx/Apache para Jitsi

Si Jitsi está detrás de un proxy reverso, asegúrate de que:

- Todas las solicitudes se redirijan a HTTPS
- Los headers `X-Forwarded-Proto` estén configurados correctamente
- No haya recursos HTTP siendo servidos

## Solución Alternativa: Configurar Jitsi para HTTPS Completo

Si quieres que el iframe funcione directamente (sin abrir ventana separada), necesitas:

1. **Configurar Jitsi para usar solo HTTPS internamente**
2. **Asegurar que todos los recursos (CSS, JS, imágenes) se carguen vía HTTPS**
3. **Configurar Content Security Policy (CSP) apropiadamente**

### Ejemplo de configuración Nginx para Jitsi:

```nginx
server {
    listen 8440 ssl http2;
    server_name 169.61.173.4;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    # Forzar HTTPS para todos los recursos
    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Asegurar que todos los recursos sean HTTPS
        add_header Content-Security-Policy "upgrade-insecure-requests";
    }
}
```

## Comportamiento Actual

Con la solución implementada:

1. **Cuando OpenEMR está en HTTPS:**
   - El iframe se oculta automáticamente
   - Se muestra un mensaje explicativo
   - Jitsi se abre automáticamente en una ventana separada después de 1 segundo
   - La ventana se posiciona al lado de la ventana principal

2. **Cuando OpenEMR está en HTTP:**
   - El iframe se intenta cargar normalmente
   - Si falla, se muestra el fallback

## Verificar que Funciona

1. Abre la página de televisita unificada
2. Deberías ver el mensaje: "Due to browser security, the video conference will open in a separate window"
3. Después de 1 segundo, se abrirá automáticamente la ventana de Jitsi
4. La ventana de Jitsi debería cargar sin errores de contenido mixto

## Si el Problema Persiste

Si aún ves errores de contenido mixto:

1. **Verifica la consola del navegador** (F12) para ver qué recursos HTTP se están bloqueando
2. **Verifica la configuración de Jitsi** para asegurar que todos los recursos sean HTTPS
3. **Revisa los logs de Jitsi** para ver si hay errores de configuración

## Contacto

Si necesitas ayuda adicional, verifica:
- Logs de Jitsi: `/var/log/jitsi/`
- Configuración de Jitsi: `/etc/jitsi/`
- Documentación oficial de Jitsi: https://jitsi.org/

