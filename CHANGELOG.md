# Changelog

Cambios notables en `facturador-electronico-cr` serán documentados aquí.

Actualizaciones deben seguir los principios en [Mantenga un CHANGELOG](https://keepachangelog.com/es-ES/1.0.0/).

## [Unreleased]

### Added

- Cuando ocurre un error fatal al comunicarse con Hacienda, quitar el
comprobante de la cola de envío
- Desactivar reintento de envios al haber fallos por 3 días
- El firmador de xmls tira una excepción si la llave criptográfica está vencida.
- La función `Storage::runMigrations()` para reemplazar `Storage::run_migrations()`

### Changed

- Las columnas de `clave` en la base de datos fueron cambiados a DECIMAL
para ahorrar espacio
- Optimizaciones varias en el firmador de xmls
- Limpieza general de código
- Actualzación de las dependencias

### Removed

- Soporte para crear xmls de la versión 4.2 fue eliminado

### Deprecated

- La función `Storage::run_migrations()` para actualizar la base de datos
va a ser eliminada en una versión futura. Use `Storage::runMigrations()`.

## [3.1.1] - 2020-01-07

### Fixed

- No terminar en error cuando la respuesta de Hacienda viene sin el xml (sucede)

## [3.1.0] - 2019-11-02

### Added

- Comprobar que un comprobante esté aceptado en Hacienda antes de intentar recepcionarlo

### Changed

- Limites de consultas al API de Hacienda
únicamente se aplican en el ambiente de Staging
