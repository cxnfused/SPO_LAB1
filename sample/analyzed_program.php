<?php
declare(strict_types=1);
const VIP_DISCOUNT = 0.15;
const LARGE_ORDER_DISCOUNT = 0.10;
const VAT_RATE = 0.20;
function calculateDiscount(array $order): float
{
    $total = $order['price'] * $order['quantity'];
    if ($order['customer'] === 'vip' && $total > 0) {
        $discount = $total * VIP_DISCOUNT;
    } elseif ($total >= 500) {
        $discount = $total * LARGE_ORDER_DISCOUNT;
    } else {
        $discount = 0.0;
    }
    return round($discount, 2);
}
function classifyOrder(float $total): string
{
    return match (true) {
        $total >= 800 => 'крупный',
        $total >= 300 => 'средний',
        default => 'малый',
    };
}
function calculateAverage(array $values): float
{
    if ($values === []) {
        return 0.0;
    }
    $sum = 0.0;
    foreach ($values as $value) {
        $sum += $value;
    }
    return round($sum / count($values), 2);
}
function printReport(array $orders): void
{
    $totals = [];
    foreach ($orders as $order) {
        if ($order['quantity'] <= 0) {
            continue;
        }
        $subtotal = $order['price'] * $order['quantity'];
        $discount = calculateDiscount($order);
        $total = $subtotal - $discount;
        $totals[] = $total;
        switch ($order['status']) {
            case 'new':
                $status = 'новый';
                break;
            case 'paid':
                $status = 'оплачен';
                break;
            default:
                $status = 'неизвестен';
        }
        $priority = $total > 500 ? 'высокий' : 'обычный';
        echo '#' . $order['id'] . ': ' . classifyOrder($total) . ', ';
        echo $status . ', приоритет ' . $priority . ', сумма ' . $total . PHP_EOL;
        if ($order['id'] > 100) {
            break;
        }
    }
    echo 'Средний чек: ' . calculateAverage($totals) . PHP_EOL;
}
$orders = [
    ['id' => 1, 'price' => 120.5, 'quantity' => 2, 'customer' => 'vip', 'status' => 'paid'],
    ['id' => 2, 'price' => 80.0, 'quantity' => 0, 'customer' => 'regular', 'status' => 'new'],
    ['id' => 3, 'price' => 260.0, 'quantity' => 3, 'customer' => 'regular', 'status' => 'paid'],
];
for ($index = 0; $index < count($orders); $index++) {
    $orders[$index]['tax'] = $orders[$index]['price'] * VAT_RATE;
}
$attempt = 0;
$limit = 3;
while ($attempt < $limit) {
    echo 'Введите максимальное число заказов: ';
    $input = trim((string) fgets(STDIN));
    if (ctype_digit($input) && (int) $input > 0) {
        $limit = (int) $input;
        break;
    }
    $attempt++;
}
$countdown = 2;
do {
    echo 'Подготовка отчёта: ' . $countdown . PHP_EOL;
    $countdown--;
} while ($countdown > 0);
try {
    $selected = array_slice($orders, 0, $limit);
    printReport($selected);
} catch (Throwable $error) {
    echo 'Ошибка: ' . $error->getMessage() . PHP_EOL;
}
