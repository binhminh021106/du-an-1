<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use TCPDF; // Quan trọng: Thêm dòng này để gọi thư viện TCPDF đã cài qua Composer

class PdfController extends Controller
{
    /**
     * Tạo và xuất file PDF.
     */
    public function generatePDF()
    {
        // 1. Lấy dữ liệu (ví dụ từ database hoặc để demo)
        // $order = Order::find(1); // Ví dụ nếu bạn có Model Order
        // $customerName = $order->customer_name;
        
        // Dữ liệu ví dụ để demo
        $customerName = "Nguyễn Văn A"; 
        $html = "<h1>Hóa đơn cho: " . $customerName . "</h1>
                 <p>Đây là chi tiết đơn hàng của bạn:</p>
                 <ul>
                    <li>Sản phẩm 1 - 100.000đ</li>
                    <li>Sản phẩm 2 - 250.000đ</li>
                 </ul>
                 <p>Tổng cộng: <strong>350.000đ</strong></p>";

        // 2. Khởi tạo đối tượng TCPDF
        // Các hằng số này (PDF_PAGE_ORIENTATION, PDF_UNIT...) được định nghĩa bởi TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // 3. Cấu hình thông tin tài liệu (Tùy chọn)
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Tên Cửa Hàng Của Bạn');
        $pdf->SetTitle('Hóa đơn đơn hàng');
        $pdf->SetSubject('Chi tiết hóa đơn');

        // 4. Thêm font hỗ trợ Tiếng Việt
        // Font 'dejavusans' là font UTF-8 có sẵn trong TCPDF hỗ trợ Tiếng Việt
        $pdf->SetFont('dejavusans', '', 14, '', true);

        // 5. Thêm một trang mới
        $pdf->AddPage();

        // 6. Viết nội dung HTML vào PDF
        // TCPDF có thể render HTML/CSS cơ bản
        $pdf->writeHTML($html, true, false, true, false, '');

        // 7. Đóng và xuất file PDF ra trình duyệt
        // 'I': Hiển thị trực tiếp trên trình duyệt (Inline)
        // 'D': Bắt buộc tải file về (Download)
        // 'F': Lưu thành file trên server (File)
        $pdf->Output('hoadon.pdf', 'I');
        exit; // Dừng script sau khi xuất PDF
    }
}