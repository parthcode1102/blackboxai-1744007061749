<?php
require_once 'connect.php';
require __DIR__.'/vendor/autoload.php'; // Assuming Composer's autoload

use Dompdf\Dompdf;
use Dompdf\Options;

header('Content-Type: application/json');

try {
    // Verify authentication
    if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
        throw new Exception('Authentication required');
    }

    $orderId = $_GET['order_id'] ?? null;
    if (!$orderId) {
        throw new Exception('Order ID is required');
    }

    // Get order details
    $stmt = $pdo->prepare("
        SELECT 
            o.order_id, o.customer_name, o.contact_number, 
            o.total_amount, o.status, o.created_at,
            oi.item_id, oi.quantity, oi.price,
            i.name as item_name, i.category
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN items i ON oi.item_id = i.item_id
        WHERE o.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new Exception('Order not found');
    }

    // Calculate totals
    $subtotal = array_reduce($items, function($sum, $item) {
        return $sum + ($item['price'] * $item['quantity']);
    }, 0);
    $tax = $subtotal * 0.1; // 10% tax
    $total = $subtotal + $tax;

    // Prepare HTML content
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .header { text-align: center; margin-bottom: 20px; }
            .logo { height: 50px; }
            .invoice-title { font-size: 24px; font-weight: bold; }
            .details { margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .totals { float: right; width: 300px; }
            .footer { margin-top: 50px; font-size: 12px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="header">
            <img src="https://images.pexels.com/photos/1192043/pexels-photo-1192043.jpeg" class="logo">
            <div class="invoice-title">INVOICE</div>
        </div>

        <div class="details">
            <p><strong>Order #:</strong> '.$items[0]['order_id'].'</p>
            <p><strong>Date:</strong> '.date('F j, Y', strtotime($items[0]['created_at'])).'</p>
            <p><strong>Customer:</strong> '.$items[0]['customer_name'].'</p>
            <p><strong>Phone:</strong> '.$items[0]['contact_number'].'</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($items as $item) {
        $html .= '
                <tr>
                    <td>'.$item['item_name'].'</td>
                    <td>'.$item['category'].'</td>
                    <td>'.$item['quantity'].'</td>
                    <td>$'.number_format($item['price'], 2).'</td>
                    <td>$'.number_format($item['price'] * $item['quantity'], 2).'</td>
                </tr>';
    }

    $html .= '
            </tbody>
        </table>

        <div class="totals">
            <p><strong>Subtotal:</strong> $'.number_format($subtotal, 2).'</p>
            <p><strong>Tax (10%):</strong> $'.number_format($tax, 2).'</p>
            <p><strong>Total:</strong> $'.number_format($total, 2).'</p>
        </div>

        <div class="footer">
            <p>Thank you for your order!</p>
            <p>Rangoli Ice Cream • 123 Sweet Street • Dessertville</p>
        </div>
    </body>
    </html>';

    // Configure Dompdf
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Output the generated PDF
    $output = $dompdf->output();
    $pdfBase64 = base64_encode($output);

    echo json_encode([
        'success' => true,
        'pdf' => $pdfBase64,
        'filename' => 'invoice_'.$orderId.'.pdf'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
