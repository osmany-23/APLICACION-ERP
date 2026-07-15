<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

/**
 * StatusUpdateable Trait
 * 
 * Proporciona funcionalidad reutilizable para actualizar el estado 
 * de modelos que tengan campos status, is_active, o estado.
 * 
 * Uso:
 * use StatusUpdateable;
 * 
 * Luego en el controlador:
 * $this->updateStatus($model, $request->validated()['status']);
 */
trait StatusUpdateable
{
    /**
     * Actualiza el estado de un modelo
     * 
     * @param Model $model El modelo a actualizar
     * @param bool|int|string $status El nuevo estado
     * @param string|null $statusField El nombre del campo (por defecto intenta detectar)
     * @return JsonResponse
     */
    public function changeStatus(Model $model, $status, ?string $statusField = null): JsonResponse
    {
        try {
            // Detectar el campo de estado si no se proporciona
            if ($statusField === null) {
                $statusField = $this->detectStatusField($model);
            }

            if ($statusField === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'El modelo no tiene un campo de estado válido.',
                ], 400);
            }

            // Convertir el valor a booleano si el campo es booleano
            $statusValue = $this->normalizeStatusValue($status);

            // Actualizar el modelo
            $model->update([
                $statusField => $statusValue,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado correctamente.',
                'data' => [
                    'id' => $model->id,
                    'status' => $model->{$statusField},
                    'status_label' => $this->getStatusLabel($model->{$statusField}),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detecta automáticamente el campo de estado en el modelo
     */
    private function detectStatusField(Model $model): ?string
    {
        $possibleFields = ['status', 'is_active', 'estado', 'activo'];
        
        foreach ($possibleFields as $field) {
            if ($model->isFillable($field)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Normaliza el valor del estado a formato apropiado
     */
    private function normalizeStatusValue($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'activo', 'yes', 'on']);
        }

        return (bool) $value;
    }

    /**
     * Genera etiqueta de estado legible
     */
    private function getStatusLabel($status): string
    {
        $boolStatus = $this->normalizeStatusValue($status);
        return $boolStatus ? 'Activo' : 'Inactivo';
    }

    /**
     * Método que puede ser sobrescrito en controladores específicos
     * para personalizar la validación de estado
     */
    protected function validateStatusUpdate(Request $request): array
    {
        return $request->validate([
            'status' => 'required|boolean',
        ]);
    }
}
