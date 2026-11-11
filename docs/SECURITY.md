# Seguridad

Controles de seguridad del sistema y su relación con el diagnóstico. El Shadow IT de Cofrial (clientes, precios y direcciones en teléfonos y grupos de WhatsApp personales) es el riesgo de origen: sin control de acceso, sin cifrado corporativo, sin forma de revocar el acceso cuando alguien deja la empresa y sin registro de quién vio qué.

## 1. Marco normativo: Ley 21.719

La Ley 21.719 de protección de datos personales (Chile) fue publicada el 13 de diciembre de 2024 y entra en vigencia el 1 de diciembre de 2026 (existe un proyecto, Boletín 18.623-07, que propone postergarla un año). Para el diseño implica:

| Exigencia | Cómo responde el sistema |
| --- | --- |
| Base de licitud para tratar datos personales | Consentimiento explícito en el registro; se guarda solo lo necesario para vender y despachar |
| Medidas de seguridad técnicas y organizativas | Red privada, cifrado, roles y mínimo privilegio (secciones 3 a 6) |
| Notificación de vulneraciones a la Agencia | Monitoreo y registro de cambios que permiten detectar y acotar un incidente (sección 8) |
| Derechos de acceso, rectificación, supresión, oposición y portabilidad, con respuesta en 30 días | Los datos de un titular están en una sola base y se localizan por `cliente`; desactivar y anonimizar es posible sin perder la trazabilidad comercial |
| Sanciones de hasta 20.000 UTM | Motivo para tratar estos controles como requisitos y no como mejoras |

## 2. Qué datos se protegen

| Clase | Ejemplos | Tablas | Tratamiento |
| --- | --- | --- | --- |
| Datos personales de clientes | Nombre, RUT, correo, teléfono, dirección | `cliente`, `contacto_cliente`, `direccion_despacho`, `usuario` | Acceso por rol y cartera; nunca salen a la IA externa |
| Datos personales de trabajadores | Conductor, vendedor, posición del camión | `conductor`, `vendedor`, `telemetria` | La telemetría asociada a un conductor identificable es dato personal: acceso restringido y conservación acotada |
| Información comercial sensible | Listas de precios institucionales, pipeline | `detalle_lista_precio`, `oportunidad` | Cada cliente ve solo su lista; el vendedor, solo su cartera |
| Credenciales | Contraseñas, claves de API, usuarios de base | `usuario.password_hash`, `.env` | Hash de contraseñas; secretos fuera del repositorio |

## 3. Red

- La base de datos vive en subredes privadas **sin ruta a internet** y sin acceso público.
- El único punto de entrada desde internet es el balanceador.
- Security Groups encadenados: `sg-web` solo acepta el puerto 80 desde `sg-alb`; `sg-db` solo acepta 5432 desde `sg-web` y `sg-iot`.
- SSH (22) solo desde la IP del equipo en formato `/32`. Nunca `0.0.0.0/0`.
- La API de IA se consume solo por salida HTTPS; no hay ningún puerto de entrada asociado.

## 4. Cifrado

| Dónde | Mecanismo |
| --- | --- |
| En reposo, base de datos | Cifrado KMS en RDS (incluye respaldos y snapshots) |
| En reposo, discos | Cifrado KMS en los volúmenes EBS |
| En tránsito, usuarios | HTTPS en el balanceador. Sin dominio propio el certificado es autofirmado (importado a ACM) y el navegador mostrará un aviso en la demo |
| En tránsito, base de datos | TLS obligatorio hacia RDS (`sslmode=require` o superior) |
| Contraseñas | Hash con el algoritmo por defecto de Laravel (bcrypt o argon2); nunca reversibles |

## 5. Acceso a la base de datos: mínimo privilegio

Tres usuarios sobre el esquema de negocio, más uno para el estado interno de Grafana, para que una credencial filtrada tenga un daño acotado:

| Usuario | Puede | No puede | Lo usa |
| --- | --- | --- | --- |
| `app_rw` | Leer y escribir el esquema de negocio | Crear o alterar tablas, administrar roles, modificar o borrar historiales, escribir telemetría | Aplicación web y worker |
| `iot_writer` | Insertar en `telemetria` y `alerta`; leer la vista `iot_umbral_camion` | Leer clientes, pedidos o precios; actualizar o borrar | Contenedores del simulador |
| `bi_reader` | `SELECT` sobre las vistas `bi_*` | Escribir; leer tablas con datos personales directamente | Grafana |
| `grafana_rw` | Ser dueño de la base `grafana` (usuarios, dashboards y preferencias de Grafana, ADR-0010) | Conectarse a la base de negocio | Grafana (estado interno) |

El usuario maestro de RDS se usa solo para crear roles y ejecutar migraciones (conexión `pgsql_admin`), nunca desde la aplicación en ejecución. Los permisos se aplican con [`db/roles.sql`](../db/roles.sql) y se comprueban con [`db/pruebas_permisos.sh`](../db/pruebas_permisos.sh) (detalle en [`db/README.md`](../db/README.md)).

## 6. Acceso a la aplicación

- Cada persona tiene su propio usuario; no hay cuentas compartidas.
- Cinco roles: `administrador`, `vendedor`, `despachador`, `cliente_b2b`, `cliente_b2c`. La matriz de permisos está en [`BUSINESS_RULES.md`](BUSINESS_RULES.md#1-perfiles-y-canales) y las rutas por rol en [`API.md`](API.md).
- **Alcance de cartera (RN-10):** el vendedor ve y edita solo sus clientes. El filtro se aplica en las consultas del servidor, no en la interfaz.
- **Aislamiento entre clientes:** un cliente solo accede a sus pedidos, direcciones y tickets. Un identificador ajeno responde `404`.
- **Baja de personal:** desactivar el usuario (`usuario.activo = false`) revoca el acceso de inmediato; la cartera se reasigna cambiando `cliente.id_vendedor`. Contactos, interacciones y pedidos permanecen en Cofrial.
- **El cliente no ve al conductor:** el seguimiento muestra camión, parada, hora estimada y si hubo ruptura de frío.

Controles de aplicación exigidos en toda tarea que toque la web:

- Protección CSRF en todo formulario y petición que modifique estado.
- Consultas parametrizadas (Eloquent o *bindings*); nunca concatenar entrada del usuario en SQL.
- Escape de salida por defecto en las vistas; no usar salida sin escapar con datos del usuario.
- Validación en el servidor de toda entrada, aunque el formulario ya valide.
- Asignación masiva controlada (`$fillable`), sobre todo en `usuario`, `cliente` y `pedido`.
- Límite de intentos en inicio de sesión y en el asistente IA.
- Cookies de sesión `Secure`, `HttpOnly` y `SameSite`.
- `APP_DEBUG=false` en AWS: los errores no muestran trazas ni variables.

## 7. Secretos

- Todas las credenciales viven en un archivo `.env` **fuera del repositorio**. `.env.example` solo contiene marcadores de posición.
- Las credenciales del Learner Lab son temporales: se cargan en la terminal de quien despliega y nunca se escriben en archivos del repo.
- Las llaves SSH (`*.pem`) están ignoradas por Git.
- Si un secreto llega a un commit: se rota de inmediato y luego se limpia el historial. Borrar el archivo no basta.
- Cada contenedor recibe solo las variables que necesita: el simulador no conoce la contraseña de `app_rw` ni la clave de la IA.

## 8. Trazabilidad y monitoreo

- **Historiales de solo inserción:** `historial_estado_pedido`, `historial_etapa` y `movimiento_inventario` registran quién cambió qué y cuándo.
- **Origen del pedido:** `pedido.canal` y `pedido.id_vendedor_registra`.
- **Último acceso:** `usuario.ultimo_acceso`.
- **Infraestructura:** alarmas de CloudWatch con aviso por SNS (instancia no saludable, chequeo de estado fallido, CPU alta, poco espacio en RDS).
- Los logs de la aplicación no deben contener contraseñas, tokens, ni el cuerpo completo de peticiones con datos personales.

## 9. IA externa

- No se envían RUT, nombres, razón social, correos, teléfonos ni direcciones (RN-11). El texto libre del cliente pasa por un filtro que enmascara esos patrones antes de salir.
- El texto del cliente se trata como dato: el prompt lo delimita y la respuesta se valida contra una lista cerrada de valores. Una instrucción escrita por un cliente dentro de un ticket no puede cambiar el comportamiento del sistema más allá de una clasificación errónea.
- El asistente solo recibe datos del cliente que pregunta, obtenidos por el servidor con sus mismos permisos.
- La IA no ejecuta acciones: no cancela pedidos, no cambia precios ni estados. Solo responde o deriva a un ticket.
- Límite de uso por usuario para proteger la cuota gratuita.

## 10. Continuidad

- RDS Multi-AZ con conmutación automática y dos EC2 web en zonas distintas.
- Respaldos automáticos con retención de 7 días y recuperación a un punto en el tiempo; snapshot manual antes de la defensa.
- Meta: RPO ≤ 5 min y RTO ≤ 15 min ante la caída de una zona.

## 11. Conservación de datos

- **Telemetría:** definir un plazo de retención del detalle (propuesta: 90 días) y purgar lo anterior, conservando solo agregados.
- **Carritos abandonados y sesiones:** limpieza periódica.
- **Clientes que piden supresión:** anonimizar los datos identificatorios y conservar los pedidos para trazabilidad contable.

## 12. Riesgos aceptados en el prototipo

| Riesgo | Por qué se acepta | Mitigación |
| --- | --- | --- |
| Certificado autofirmado | No hay dominio propio | Se explica en la defensa; con dominio se usa un certificado público de ACM |
| EC2 web con IP pública | Evita el costo de un NAT Gateway | `sg-web` solo admite tráfico del balanceador |
| Credenciales de base dentro del contenedor IoT | El simulador escribe directo en RDS | Usuario `iot_writer` de mínimo privilegio |
| Sin WAF ni registro centralizado de logs | Costo y alcance del laboratorio | Validación en la aplicación; métricas básicas de CloudWatch |
| Pasarela de pago simulada | Alcance del Hito 2 | No se capturan datos de tarjetas en ningún punto |

## 13. Lista de verificación antes de desplegar

- [ ] RDS sin acceso público y en subredes privadas.
- [ ] Ningún Security Group con 22 o 5432 abiertos a `0.0.0.0/0`.
- [ ] Cifrado activado en RDS y en los volúmenes EBS.
- [ ] Conexión a la base con TLS desde la web, el simulador y Grafana.
- [ ] `APP_DEBUG=false` y `APP_ENV=production`.
- [ ] Contraseñas de `app_rw`, `iot_writer`, `bi_reader` y del administrador de Grafana distintas y no triviales.
- [ ] `git log` sin archivos `.env`, `.pem` ni claves.
- [ ] Un vendedor no puede abrir la ficha de un cliente de otra cartera (probado).
- [ ] Un cliente no puede abrir un pedido ajeno (probado).
- [ ] `iot_writer` no puede leer `cliente` (probado).
- [ ] Los seeders no contienen datos reales.

## 14. Reporte de problemas de seguridad

Avisar al equipo por un canal privado, no en un issue público. Incluir qué se observó, cómo reproducirlo y qué datos podrían estar expuestos.
