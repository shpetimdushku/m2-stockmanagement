<?php

declare(strict_types=1);

namespace Digitouch\StockManagement\Api;

interface IncomingStockManagementInterface
{
    /**
     * Aggiorna la quantità in arrivo di un prodotto.
     *
     * Nota:
     * uso volutamente i parametri snake_case per mantenere coerenza
     * con la traccia originale dell'esercizio.
     *
     * @param int $entity_id
     * @param float $incoming_qty
     * @return array
     */
    public function execute(int $entity_id, float $incoming_qty): array;
}
