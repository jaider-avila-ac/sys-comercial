<?php

namespace App\Services;

use App\Models\Compra;
use App\Repositories\CompraRepository;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CompraService
{
    public function __construct(
        private readonly CompraRepository $compraRepository,
    ) {}

    public function listar(int $empresaId): Collection
    {
        return $this->compraRepository->allByEmpresa($empresaId);
    }

    public function obtener(int $id, int $empresaId): Compra
    {
        $compra = $this->compraRepository->findById($id);

        if (! $compra || $compra->empresa_id !== $empresaId) {
            throw new HttpException(404, 'Compra no encontrada.');
        }

        return $compra;
    }

    public function crear(array $data, int $empresaId, int $usuarioId): Compra
    {
        // Calcular totales
        $subtotal = 0;
        foreach ($data['items'] as $item) {
            $subtotal += $item['cantidad'] * $item['precio_unitario'];
        }
        
        $impuestos = $data['impuestos'] ?? 0;
        $total = $subtotal + $impuestos;
        
        $saldoPendiente = 0;
        if (($data['condicion_pago'] ?? 'CONTADO') === 'CREDITO') {
            $saldoPendiente = $total;
        }

        $cabecera = [
            'empresa_id'       => $empresaId,
            'usuario_id'       => $usuarioId,
            'proveedor_id'     => $data['proveedor_id'] ?? null,
            'fecha'            => $data['fecha'],
            'condicion_pago'   => $data['condicion_pago'] ?? 'CONTADO',
            'fecha_vencimiento'=> $data['fecha_vencimiento'] ?? null,
            'subtotal'         => $subtotal,
            'impuestos'        => $impuestos,
            'total'            => $total,
            'saldo_pendiente'  => $saldoPendiente,
            'estado'           => 'PENDIENTE',
            'notas'            => $data['notas'] ?? null,
            'numero'           => null,
        ];

        return $this->compraRepository->create($cabecera, $data['items']);
    }

    public function confirmar(int $id, int $empresaId, int $usuarioId, ?array $archivoData = null): Compra
    {
        $compra = $this->obtener($id, $empresaId);
        
        if ($compra->numero !== null) {
            throw new HttpException(422, 'La compra ya ha sido confirmada.');
        }

        // Generar número de compra
        $ultimaCompra = Compra::where('empresa_id', $empresaId)
            ->whereNotNull('numero')
            ->orderBy('id', 'desc')
            ->first();
        
        $consecutivo = $ultimaCompra ? intval(substr($ultimaCompra->numero, -6)) + 1 : 1;
        $numero = 'COMP-' . str_pad($consecutivo, 6, '0', STR_PAD_LEFT);

        return $this->compraRepository->confirmar($id, $numero, $usuarioId, $archivoData);
    }

    public function registrarPago(int $id, array $pagoData, int $empresaId, int $usuarioId): Compra
    {
        $compra = $this->obtener($id, $empresaId);
        
        if (in_array($compra->estado, ['PAGADA', 'ANULADA'])) {
            throw new HttpException(422, 'No se puede registrar pagos en compras pagadas o anuladas.');
        }

        $monto = $pagoData['monto'];
        $saldoActual = (float) $compra->saldo_pendiente;
        
        if ($monto > $saldoActual) {
            throw new HttpException(422, 'El monto del pago no puede ser mayor al saldo pendiente.');
        }

        $egresoData = [
            'fecha'       => $pagoData['fecha'],
            'descripcion' => $pagoData['descripcion'] ?? "Pago compra {$compra->numero}",
            'medio_pago'  => $pagoData['medio_pago'],
            'notas'       => $pagoData['notas'] ?? null,
        ];

        return $this->compraRepository->registrarPago($id, $monto, $empresaId, $usuarioId, $egresoData);
    }

    public function registrarPagoConArchivo(
        int $id, 
        float $monto, 
        string $fecha, 
        string $medioPago, 
        string $descripcion, 
        ?string $notas,
        int $empresaId, 
        int $usuarioId,
        ?array $archivoData
    ): Compra {
        $compra = $this->obtener($id, $empresaId);
        
        if (in_array($compra->estado, ['PAGADA', 'ANULADA'])) {
            throw new HttpException(422, 'No se puede registrar pagos en compras pagadas o anuladas.');
        }

        $saldoActual = (float) $compra->saldo_pendiente;
        
        if ($monto > $saldoActual) {
            throw new HttpException(422, 'El monto del pago no puede ser mayor al saldo pendiente.');
        }

        $egresoData = [
            'fecha'          => $fecha,
            'descripcion'    => $descripcion,
            'medio_pago'     => $medioPago,
            'notas'          => $notas,
        ];

        if ($archivoData) {
            $egresoData['archivo_path'] = $archivoData['path'];
            $egresoData['archivo_mime'] = $archivoData['mime'];
            $egresoData['archivo_nombre'] = $archivoData['nombre'];
        }

        return $this->compraRepository->registrarPago($id, $monto, $empresaId, $usuarioId, $egresoData);
    }

    public function anular(int $id, int $empresaId, int $usuarioId): Compra
    {
        $compra = $this->obtener($id, $empresaId);
        
        if ($compra->estado === 'ANULADA') {
            throw new HttpException(422, 'La compra ya está anulada.');
        }

        return $this->compraRepository->anular($id, $empresaId, $usuarioId);
    }
}