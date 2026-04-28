<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property int|null $empresa_id NULL si acción de SUPER_ADMIN
 * @property int $usuario_id
 * @property string $entidad
 * @property string $accion
 * @property int|null $entidad_id
 * @property string|null $descripcion
 * @property string|null $ip
 * @property \Illuminate\Support\Carbon $ocurrido_en
 * @property-read \App\Models\Usuario $usuario
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auditoria newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auditoria newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auditoria query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auditoria whereAccion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auditoria whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auditoria whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auditoria whereEntidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auditoria whereEntidadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auditoria whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auditoria whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auditoria whereOcurridoEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auditoria whereUsuarioId($value)
 */
	class Auditoria extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property bool $is_activo
 * @property string|null $api_key
 * @property string|null $sender_name
 * @property string|null $sender_email
 * @property int|null $template_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Empresa $empresa
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BrevoConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BrevoConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BrevoConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BrevoConfig whereApiKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BrevoConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BrevoConfig whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BrevoConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BrevoConfig whereIsActivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BrevoConfig whereSenderEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BrevoConfig whereSenderName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BrevoConfig whereTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BrevoConfig whereUpdatedAt($value)
 */
	class BrevoConfig extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property int $usuario_id
 * @property string $origen_tipo
 * @property int $origen_id ID en la tabla origen
 * @property string $descripcion
 * @property numeric $monto Positivo=ingreso | Negativo=egreso
 * @property \Illuminate\Support\Carbon $fecha
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\Empresa $empresa
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CajaMovimiento newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CajaMovimiento newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CajaMovimiento query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CajaMovimiento whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CajaMovimiento whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CajaMovimiento whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CajaMovimiento whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CajaMovimiento whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CajaMovimiento whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CajaMovimiento whereOrigenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CajaMovimiento whereOrigenTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CajaMovimiento whereUsuarioId($value)
 */
	class CajaMovimiento extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property string $nombre_razon_social
 * @property string|null $contacto
 * @property string $tipo_documento
 * @property string|null $num_documento
 * @property string|null $email
 * @property string|null $telefono
 * @property \App\Models\Empresa $empresa
 * @property string|null $direccion
 * @property bool $is_activo
 * @property numeric $saldo_a_favor
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente whereContacto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente whereDireccion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente whereEmpresa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente whereIsActivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente whereNombreRazonSocial($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente whereNumDocumento($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente whereSaldoAFavor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente whereTelefono($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente whereTipoDocumento($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cliente whereUpdatedAt($value)
 */
	class Cliente extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property int|null $proveedor_id
 * @property int $usuario_id
 * @property string $numero
 * @property \Illuminate\Support\Carbon $fecha
 * @property string $condicion_pago
 * @property \Illuminate\Support\Carbon|null $fecha_vencimiento
 * @property numeric $subtotal
 * @property numeric $impuestos
 * @property numeric $total
 * @property numeric $saldo_pendiente
 * @property string $estado
 * @property string|null $notas
 * @property string|null $archivo_path
 * @property string|null $archivo_mime
 * @property string|null $archivo_nombre
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EgresoCompra> $egresos
 * @property-read int|null $egresos_count
 * @property-read \App\Models\Empresa $empresa
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompraItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\Proveedor|null $proveedor
 * @property-read \App\Models\Usuario $usuario
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereArchivoMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereArchivoNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereArchivoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereCondicionPago($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereFechaVencimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereImpuestos($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereNotas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereNumero($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereProveedorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereSaldoPendiente($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Compra whereUsuarioId($value)
 */
	class Compra extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $compra_id
 * @property int $item_id
 * @property int $cantidad
 * @property numeric $precio_unitario
 * @property numeric $subtotal
 * @property-read \App\Models\Compra $compra
 * @property-read \App\Models\Item $item
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompraItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompraItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompraItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompraItem whereCantidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompraItem whereCompraId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompraItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompraItem whereItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompraItem wherePrecioUnitario($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompraItem whereSubtotal($value)
 */
	class CompraItem extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property int $cliente_id
 * @property int $usuario_id
 * @property string $numero
 * @property string $estado
 * @property \Illuminate\Support\Carbon $fecha
 * @property \Illuminate\Support\Carbon|null $fecha_vencimiento
 * @property string|null $notas
 * @property numeric $subtotal
 * @property numeric $total_descuentos
 * @property numeric $total_iva
 * @property numeric $total
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Cliente $cliente
 * @property-read \App\Models\Empresa $empresa
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CotizacionLinea> $lineas
 * @property-read int|null $lineas_count
 * @property-read \App\Models\Usuario $usuario
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion whereClienteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion whereFechaVencimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion whereNotas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion whereNumero($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion whereTotalDescuentos($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion whereTotalIva($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cotizacion whereUsuarioId($value)
 */
	class Cotizacion extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $cotizacion_id
 * @property int|null $item_id
 * @property string|null $descripcion_manual
 * @property int $cantidad
 * @property numeric $valor_unitario
 * @property numeric $descuento
 * @property numeric $iva_pct
 * @property numeric $iva_valor
 * @property numeric $total_linea
 * @property-read \App\Models\Cotizacion $cotizacion
 * @property-read \App\Models\Item|null $item
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionLinea newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionLinea newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionLinea query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionLinea whereCantidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionLinea whereCotizacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionLinea whereDescripcionManual($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionLinea whereDescuento($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionLinea whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionLinea whereItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionLinea whereIvaPct($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionLinea whereIvaValor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionLinea whereTotalLinea($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CotizacionLinea whereValorUnitario($value)
 */
	class CotizacionLinea extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property int $usuario_id
 * @property int $compra_id
 * @property \Illuminate\Support\Carbon $fecha
 * @property string $descripcion
 * @property numeric $monto
 * @property string|null $notas
 * @property string|null $medio_pago
 * @property string|null $archivo_path
 * @property string|null $archivo_mime
 * @property string|null $archivo_nombre
 * @property string $estado
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Compra $compra
 * @property-read \App\Models\Empresa $empresa
 * @property-read \App\Models\Usuario $usuario
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra whereArchivoMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra whereArchivoNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra whereArchivoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra whereCompraId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra whereMedioPago($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra whereNotas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoCompra whereUsuarioId($value)
 */
	class EgresoCompra extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property int $usuario_id
 * @property \Illuminate\Support\Carbon $fecha
 * @property string $descripcion
 * @property numeric $monto
 * @property string|null $notas
 * @property string|null $archivo_path
 * @property string|null $archivo_mime
 * @property string|null $archivo_nombre
 * @property string $estado
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Empresa $empresa
 * @property-read \App\Models\Usuario $usuario
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual whereArchivoMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual whereArchivoNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual whereArchivoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual whereNotas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EgresoManual whereUsuarioId($value)
 */
	class EgresoManual extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $nombre
 * @property string $nit
 * @property string $email
 * @property string|null $telefono
 * @property string|null $direccion
 * @property string|null $logo_path
 * @property string|null $logo_mime
 * @property \Illuminate\Support\Carbon|null $logo_updated_at
 * @property bool $is_activa
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Usuario> $usuarios
 * @property-read int|null $usuarios_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa whereDireccion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa whereIsActiva($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa whereLogoMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa whereLogoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa whereLogoUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa whereNit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa whereTelefono($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Empresa whereUpdatedAt($value)
 */
	class Empresa extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $empresa_id
 * @property int $total_clientes
 * @property int $total_items
 * @property int $cotizaciones_activas Estado EMITIDA
 * @property int $facturas_borrador
 * @property int $facturas_emitidas
 * @property numeric $total_facturado Suma de facturas EMITIDAS
 * @property numeric $total_pagado Suma de total_pagado en facturas
 * @property numeric $saldo_pendiente Suma de saldo en facturas EMITIDAS
 * @property numeric $ingresos_facturas ingresos_pagos activos
 * @property numeric $ingresos_mostrador ingresos_mostrador activos
 * @property numeric $ingresos_manuales ingresos_manuales activos
 * @property numeric $total_en_caja ingresos_facturas + ingresos_mostrador + ingresos_manuales
 * @property numeric $egresos_compras egresos_compras activos
 * @property numeric $egresos_manuales_tot egresos_manuales activos
 * @property numeric $total_egresos egresos_compras + egresos_manuales
 * @property numeric $balance_real total_en_caja - total_egresos
 * @property \Illuminate\Support\Carbon $ultima_actividad
 * @property-read \App\Models\Empresa $empresa
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereBalanceReal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereCotizacionesActivas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereEgresosCompras($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereEgresosManualesTot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereFacturasBorrador($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereFacturasEmitidas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereIngresosFacturas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereIngresosManuales($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereIngresosMostrador($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereSaldoPendiente($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereTotalClientes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereTotalEgresos($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereTotalEnCaja($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereTotalFacturado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereTotalItems($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereTotalPagado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmpresaResumen whereUltimaActividad($value)
 */
	class EmpresaResumen extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property int $cliente_id
 * @property int $usuario_id
 * @property int|null $cotizacion_id NULL = factura directa
 * @property string $numero
 * @property string $estado
 * @property \Illuminate\Support\Carbon $fecha
 * @property string|null $notas
 * @property numeric $subtotal
 * @property numeric $total_descuentos
 * @property numeric $total_iva
 * @property numeric $total
 * @property numeric $total_pagado
 * @property numeric $saldo
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Cliente $cliente
 * @property-read \App\Models\Cotizacion|null $cotizacion
 * @property-read \App\Models\Empresa $empresa
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FacturaLinea> $lineas
 * @property-read int|null $lineas_count
 * @property-read \App\Models\Usuario $usuario
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereClienteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereCotizacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereNotas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereNumero($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereSaldo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereTotalDescuentos($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereTotalIva($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereTotalPagado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Factura whereUsuarioId($value)
 */
	class Factura extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $factura_id
 * @property int|null $item_id
 * @property string|null $descripcion_manual
 * @property int $cantidad
 * @property numeric $valor_unitario
 * @property numeric $descuento
 * @property numeric $iva_pct
 * @property numeric $iva_valor
 * @property numeric $total_linea
 * @property-read \App\Models\Factura $factura
 * @property-read \App\Models\Item|null $item
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacturaLinea newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacturaLinea newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacturaLinea query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacturaLinea whereCantidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacturaLinea whereDescripcionManual($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacturaLinea whereDescuento($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacturaLinea whereFacturaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacturaLinea whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacturaLinea whereItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacturaLinea whereIvaPct($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacturaLinea whereIvaValor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacturaLinea whereTotalLinea($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacturaLinea whereValorUnitario($value)
 */
	class FacturaLinea extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property int $usuario_id
 * @property \Illuminate\Support\Carbon $fecha
 * @property string $descripcion
 * @property numeric $monto
 * @property string|null $notas
 * @property string|null $archivo_path
 * @property string|null $archivo_mime
 * @property string|null $archivo_nombre
 * @property string $estado
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Empresa $empresa
 * @property-read string|null $archivo_url
 * @property-read \App\Models\Usuario $usuario
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual whereArchivoMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual whereArchivoNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual whereArchivoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual whereNotas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoManual whereUsuarioId($value)
 */
	class IngresoManual extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property int $usuario_id
 * @property string $numero MOS-0001
 * @property \Illuminate\Support\Carbon $fecha
 * @property string $descripcion
 * @property numeric $monto Total cobrado (subtotal + iva)
 * @property string|null $notas
 * @property string $forma_pago
 * @property string|null $referencia
 * @property int|null $item_id NULL si venta libre sin item del catálogo
 * @property int $cantidad
 * @property numeric $precio_unitario
 * @property numeric $iva_pct
 * @property string|null $archivo_path
 * @property string|null $archivo_mime
 * @property string|null $archivo_nombre
 * @property string $estado
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Empresa $empresa
 * @property-read \App\Models\Item|null $item
 * @property-read \App\Models\Usuario $usuario
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereArchivoMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereArchivoNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereArchivoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereCantidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereFormaPago($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereIvaPct($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereNotas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereNumero($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador wherePrecioUnitario($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereReferencia($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoMostrador whereUsuarioId($value)
 */
	class IngresoMostrador extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property int $usuario_id
 * @property string $numero REC-0001
 * @property \Illuminate\Support\Carbon $fecha
 * @property string $descripcion
 * @property numeric $monto
 * @property string|null $notas
 * @property string $forma_pago
 * @property string|null $referencia
 * @property string|null $archivo_path
 * @property string|null $archivo_mime
 * @property string|null $archivo_nombre
 * @property string $estado
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PagoAplicacion> $aplicaciones
 * @property-read int|null $aplicaciones_count
 * @property-read \App\Models\Empresa $empresa
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Factura> $facturas
 * @property-read int|null $facturas_count
 * @property-read \App\Models\Usuario $usuario
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereArchivoMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereArchivoNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereArchivoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereFormaPago($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereNotas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereNumero($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereReferencia($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IngresoPago whereUsuarioId($value)
 */
	class IngresoPago extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property int $item_id
 * @property int $unidades_actuales
 * @property int $unidades_minimas
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Item $item
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventario newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventario newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventario query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventario whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventario whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventario whereItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventario whereUnidadesActuales($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventario whereUnidadesMinimas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventario whereUpdatedAt($value)
 */
	class Inventario extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property int $item_id
 * @property int $usuario_id
 * @property string $tipo
 * @property string|null $motivo
 * @property string|null $referencia_tipo
 * @property int|null $referencia_id
 * @property int $unidades
 * @property int $unidades_resultantes
 * @property \Illuminate\Support\Carbon $ocurrido_en
 * @property-read \App\Models\Item $item
 * @property-read \App\Models\Usuario $usuario
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventarioMovimiento newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventarioMovimiento newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventarioMovimiento query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventarioMovimiento whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventarioMovimiento whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventarioMovimiento whereItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventarioMovimiento whereMotivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventarioMovimiento whereOcurridoEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventarioMovimiento whereReferenciaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventarioMovimiento whereReferenciaTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventarioMovimiento whereTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventarioMovimiento whereUnidades($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventarioMovimiento whereUnidadesResultantes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventarioMovimiento whereUsuarioId($value)
 */
	class InventarioMovimiento extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property int|null $proveedor_id
 * @property string $tipo
 * @property string $nombre
 * @property string|null $descripcion
 * @property numeric $precio_compra
 * @property numeric $precio_venta_sugerido
 * @property bool $controla_inventario
 * @property string $unidad
 * @property bool $is_activo
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Empresa $empresa
 * @property-read \App\Models\Inventario|null $inventario
 * @property-read \App\Models\Proveedor|null $proveedor
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereControlaInventario($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereIsActivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item wherePrecioCompra($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item wherePrecioVentaSugerido($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereProveedorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereUnidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Item whereUpdatedAt($value)
 */
	class Item extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $empresa_id
 * @property int|null $usuario_id NULL en LOGIN_FAIL si el email no existe
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string $evento
 * @property \Illuminate\Support\Carbon $ocurrido_en
 * @property-read \App\Models\Usuario|null $usuario
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginLog whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginLog whereEvento($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginLog whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginLog whereOcurridoEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginLog whereUsuarioId($value)
 */
	class LoginLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property string|null $tipo
 * @property string $prefijo
 * @property int $consecutivo
 * @property int $relleno
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Empresa $empresa
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Numeracion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Numeracion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Numeracion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Numeracion whereConsecutivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Numeracion whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Numeracion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Numeracion wherePrefijo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Numeracion whereRelleno($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Numeracion whereTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Numeracion whereUpdatedAt($value)
 */
	class Numeracion extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $ingreso_pago_id
 * @property int $factura_id
 * @property int $empresa_id
 * @property numeric $monto
 * @property-read \App\Models\Factura $factura
 * @property-read \App\Models\IngresoPago $ingresoPago
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PagoAplicacion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PagoAplicacion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PagoAplicacion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PagoAplicacion whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PagoAplicacion whereFacturaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PagoAplicacion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PagoAplicacion whereIngresoPagoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PagoAplicacion whereMonto($value)
 */
	class PagoAplicacion extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $empresa_id
 * @property string $nombre
 * @property string|null $nit
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $contacto
 * @property string|null $direccion
 * @property string|null $ciudad
 * @property int|null $tiempo_entrega_dias
 * @property string|null $notas
 * @property bool $is_activo
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Empresa $empresa
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item> $items
 * @property-read int|null $items_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor whereCiudad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor whereContacto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor whereDireccion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor whereIsActivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor whereNit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor whereNotas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor whereTelefono($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor whereTiempoEntregaDias($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proveedor whereUpdatedAt($value)
 */
	class Proveedor extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $empresa_id
 * @property int $usuario_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $pais
 * @property string|null $ciudad
 * @property \Illuminate\Support\Carbon $iniciado_en
 * @property-read \App\Models\Usuario $usuario
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SesionLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SesionLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SesionLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SesionLog whereCiudad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SesionLog whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SesionLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SesionLog whereIniciadoEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SesionLog whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SesionLog wherePais($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SesionLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SesionLog whereUsuarioId($value)
 */
	class SesionLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $empresa_id NULL = SUPER_ADMIN global
 * @property string $nombres
 * @property string $apellidos
 * @property string $email
 * @property string $password_hash
 * @property string $rol
 * @property bool $is_activo
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Empresa|null $empresa
 * @property-read string $nombre_completo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereApellidos($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereEmpresaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereIsActivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereNombres($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario wherePasswordHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereRol($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereUpdatedAt($value)
 */
	class Usuario extends \Eloquent {}
}

