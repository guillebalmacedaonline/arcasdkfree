# Changelog

## 7.1.0

Todos los cambios son retrocompatibles: sin las opciones nuevas el comportamiento es el de 7.0.x.

### Nuevo
- **RG ARCA 5616**: `wsfe.wsdl` y `wsfe-production.wsdl` actualizados a las versiones vigentes de ARCA.
  `FECAEDetRequest` ahora incluye `CondicionIVAReceptorId` (antes `SoapClient` lo descartaba en silencio),
  y tambien `CanMisMonExt`, `PeriodoAsoc` y `Actividades`. `MonCotiz` pasa a ser opcional.
  No se modifico ninguna operacion existente.
- `ElectronicBilling::GetCondicionIvaReceptorTypes($claseCmp = null)` (`FEParamGetCondicionIvaReceptor`):
  devuelve la lista `Id` / `Desc` / `Cmp_Clase`.
- `ElectronicBilling::CreateVoucher($data)` (sin `$return_response`) devuelve ademas la clave
  `Observaciones` (array de `['Code' => .., 'Msg' => ..]`, vacio si no hay). `CAE` y `CAEFchVto` no cambian.
- Opcion `soap_timeout` (segundos): aplica `connection_timeout`, el timeout http del `stream_context`
  y `default_socket_timeout` (temporalmente, durante la llamada) en WSFE, padron y WSAA.
  Al vencer se propaga el `SoapFault` de siempre.
- Opcion `cafile`: ruta del bundle de CA (por defecto `/etc/ssl/certs/ca-certificates.crt`).
- Opciones `ta_get` (`function ($key)`: devuelve el XML del TA o `null`) y `ta_put`
  (`function ($key, $xml)`) para guardar el TA en un almacenamiento propio.
  `$key` es `TA-{CUIT}-{servicio}[-production]`. Sin callbacks se usan archivos en `ta_folder`.

### Cambios
- `CreateVoucher`: `CbtesAsoc` se envuelve en `CbteAsoc` solo si no viene ya con la clave `CbteAsoc`.
- Renovacion del TA segura ante concurrencia: `flock` por CUIT+servicio, relectura del TA al obtener el lock,
  archivos temporales unicos para el TRA y escritura atomica del TA. Un TA con XML corrupto se renueva
  en lugar de lanzar excepcion.
- PHP 8.2+: se declaran `Afip::$options` y `AfipWebService::$soap_client` y se agrega
  `#[\AllowDynamicProperties]` a `Afip`, eliminando los deprecations. No cambia el PHP minimo.
