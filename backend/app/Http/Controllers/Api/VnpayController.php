<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Jobs\ProcessOrder;
use Illuminate\Support\Facades\Log;

class VnpayController extends Controller
{
    public function createPayment(Request $request)
    {
        $request->validate(['order_id' => 'required|exists:orders,id']);
        
        $order = Order::findOrFail($request->order_id);

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $vnp_TmnCode = trim(config('services.vnpay.tmn_code', ''));
        $vnp_HashSecret = trim(config('services.vnpay.hash_secret', ''));
        $vnp_Url = trim(config('services.vnpay.url', ''));
        $vnp_Returnurl = route('vnpay.return');

        $vnp_TxnRef = (string)$order->id . '_' . time(); // Thêm time() để id giao dịch unique bên VNPAY
        // Loại bỏ khoảng trắng trong OrderInfo để tránh lỗi URL Encode giữa PHP và VNPAY
        $vnp_OrderInfo = 'Thanh_toan_don_hang_' . $order->id;
        $vnp_OrderType = 'billpayment';
        $vnp_Amount = (int) ($order->total_amount * 100); // VNPAY yêu cầu số tiền nhân với 100
        $vnp_Locale = 'vn';
        $vnp_BankCode = '';
        
        // VNPAY yêu cầu IPv4, nếu là ::1 hoặc định dạng lạ thì chuyển thành 127.0.0.1
        $vnp_IpAddr = $request->ip();
        if (!filter_var($vnp_IpAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $vnp_IpAddr = '127.0.0.1';
        }

        // Bắt buộc timezone Việt Nam để CreateDate không bị lệch múi giờ (Gốc của mọi lỗi VNPAY)
        $old_tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $startTime = date("YmdHis");
        date_default_timezone_set($old_tz);

        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => $startTime,
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef,
        );

        // VNPAY bắt buộc: Loại bỏ các tham số có giá trị rỗng trước khi tạo chữ ký
        $inputData = array_filter($inputData, function($value) {
            return $value !== '' && $value !== null;
        });

        // Sắp xếp dữ liệu theo key tăng dần (yêu cầu bắt buộc của VNPAY)
        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $vnp_Url . "?" . rtrim($query, '&');
        if (isset($vnp_HashSecret)) {
            // Hash dữ liệu bằng SHA512 để tạo chữ ký an toàn (vnp_SecureHash)
            $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
            $vnp_Url .= '&vnp_SecureHash=' . $vnpSecureHash;
        }

        return response()->json([
            'status' => 'success',
            'url' => $vnp_Url
        ]);
    }

    public function vnpayReturn(Request $request)
    {
        $vnp_SecureHash = $request->input('vnp_SecureHash');
        $inputData = array();
        foreach ($request->all() as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }
        
        unset($inputData['vnp_SecureHash']);
        ksort($inputData);
        $i = 0;
        $hashData = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, config('services.vnpay.hash_secret', ''));
        
        // Return URL chỉ dùng để chuyển hướng người dùng về trang giao diện
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173') . '/orders';

        if ($secureHash == $vnp_SecureHash) {
            if ($request->input('vnp_ResponseCode') == '00') {
                return redirect($frontendUrl . '?payment=success');
            } else {
                return redirect($frontendUrl . '?payment=failed');
            }
        } else {
            return redirect($frontendUrl . '?payment=invalid_signature');
        }
    }

    public function vnpayIpn(Request $request)
    {
        $inputData = array();
        $returnData = array();
        foreach ($request->all() as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }

        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';
        unset($inputData['vnp_SecureHash']);
        ksort($inputData);
        $i = 0;
        $hashData = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        // Verify checksum
        $secureHash = hash_hmac('sha512', $hashData, config('services.vnpay.hash_secret', ''));
        $vnp_Amount = $inputData['vnp_Amount']/100;
        
        $orderIdArr = explode('_', $inputData['vnp_TxnRef']);
        $orderId = $orderIdArr[0];

        try {
            if ($secureHash == $vnp_SecureHash) {
                $order = Order::find($orderId);
                if ($order != null) {
                    if ($order->total_amount == $vnp_Amount) {
                        if ($order->status !== 'completed' && $order->status !== 'processing') {
                            if ($inputData['vnp_ResponseCode'] == '00' && $inputData['vnp_TransactionStatus'] == '00') {
                                // Giao dịch thành công
                                $order->status = 'processing';
                                $order->payment_method = 'VNPAY';
                                $order->save();

                                // Dispatch Job để xử lý (trừ tồn kho, lưu thay đổi, gửi mail...)
                                ProcessOrder::dispatch($order->id);

                                $returnData['RspCode'] = '00';
                                $returnData['Message'] = 'Confirm Success';
                            } else {
                                // Giao dịch thất bại
                                $order->status = 'cancelled';
                                $order->save();
                                $returnData['RspCode'] = '00';
                                $returnData['Message'] = 'Confirm Success';
                            }
                        } else {
                            $returnData['RspCode'] = '02';
                            $returnData['Message'] = 'Order already confirmed';
                        }
                    } else {
                        $returnData['RspCode'] = '04';
                        $returnData['Message'] = 'invalid amount';
                    }
                } else {
                    $returnData['RspCode'] = '01';
                    $returnData['Message'] = 'Order not found';
                }
            } else {
                $returnData['RspCode'] = '97';
                $returnData['Message'] = 'Invalid signature';
            }
        } catch (\Exception $e) {
            $returnData['RspCode'] = '99';
            $returnData['Message'] = 'Unknown error';
            Log::error('VNPAY IPN Error: ' . $e->getMessage());
        }

        return response()->json($returnData);
    }
}
