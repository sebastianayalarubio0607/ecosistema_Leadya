# Actualizacion del formulario de integraciones por FTP/SFTP

El paquete contiene las 10 vistas actuales y una comprobacion de su contenido.
La ruta del navegador sigue siendo `/integrations/create`.

## Rutas que deben conservarse

```text
resources/views/integrations/create.blade.php
resources/views/integrations/edit.blade.php
resources/views/integrations/_form.blade.php
resources/views/livewire/integrations/form.blade.php
resources/views/livewire/integrations/partials/*.blade.php
tools/check-integration-views.php
tools/integration-views-manifest.json
```

`create` y `edit` montan `<livewire:integrations.form>`. Volt busca ese componente
en `resources/views/livewire/integrations/form.blade.php`. `_form.blade.php`
es una entrada de compatibilidad que tambien monta el componente nuevo.
No mover el componente a la carpeta del archivo de compatibilidad ni cambiar sus nombres.

## Subida

1. Descomprimir el ZIP en el equipo local.
2. Subir su contenido dentro de `/var/www/leadsquality.leadsya.com/`, al mismo nivel
   que `artisan`. Fusionar las carpetas y sobrescribir los archivos incluidos;
   no reemplazar ni borrar las carpetas completas del servidor.
3. Confirmar que no se creo una carpeta intermedia con el nombre del ZIP.
4. No subir `storage/framework/views`, `bootstrap/cache`, `public/hot` ni `.env`
   desde el equipo local. Este paquete no contiene esos archivos.
5. Ejecutar en la terminal del servidor, una linea a la vez:

```bash
cd /var/www/leadsquality.leadsya.com
php tools/check-integration-views.php
```

Los 10 archivos deben mostrar `OK`. `FALTA` o `DIFERENTE` indica una subida
incompleta o un contenido distinto al paquete. Las huellas normalizan CRLF/LF.
El informe tambien indica que rutas usa Laravel en consola, la carpeta de cache
y si encuentra contenido antiguo en las vistas compiladas de crear/editar.
El comando es de lectura; no muestra credenciales ni modifica vistas o caches.

Con todos los archivos correctos, limpiar las vistas y recargar el servicio PHP
8.2-FPM, cuyo nombre se confirmo en este servidor:

```bash
php artisan view:clear
systemctl reload php8.2-fpm
```

Abrir nuevamente la URL completa en el navegador. El formulario nuevo muestra
`Datos generales`, `Descripcion` y `Estado`. Su HTML contiene el atributo
`data-integration-form-version="2026-09-11-livewire"`.

Si el informe da todos los archivos correctos pero se sigue viendo el formulario
antiguo, compartir el informe y una captura nueva. La comprobacion de consola no
demuestra que Nginx/PHP-FPM sirvan esa misma carpeta ni descarta una cache HTTP.
No volver a renombrar las vistas para intentar resolver ese caso.

## Alcance de la revision local

Se verificaron rutas, controlador, registro de Volt, vistas de crear/editar y
compatibilidad con el contenedor anterior. Pasaron 6 pruebas con 49 aserciones,
incluida la renderizacion de ambas paginas completas y una actualizacion Livewire.
El CSS compilado local contiene la clase de dos columnas para pantallas medianas.
La falta de CSS puede cambiar la disposicion, pero no explica la ausencia del
campo Descripcion ni la presencia de las etiquetas antiguas.

No se ha verificado el sistema de archivos ni el proceso web del hosting.
La implementacion instalada de Blade compara fechas de modificacion, no el
contenido: conservar fechas anteriores durante FTP puede mantener una cache vieja.
Es una causa posible que debe comprobarse con el informe del servidor.
