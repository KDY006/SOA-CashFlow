/**
 * OTP Controller
 * Quản lý sinh mã OTP, xác thực và kiểm tra trạng thái OTP
 */
const otpService = require('../services/otpService');

exports.generateOtp = (req, res) => {
  const { userId, action, transactionId, amount, email } = req.body;

  if (!userId) {
    return res.status(400).json({
      success: false,
      error: 'VALIDATION_ERROR',
      message: 'userId là bắt buộc để sinh mã OTP',
    });
  }

  const result = otpService.createOtp({
    userId,
    action: action || 'PAYMENT',
    transactionId,
    amount,
    email,
  });

  return res.status(201).json({
    success: true,
    message: 'Mã xác thực OTP đã được sinh và phát sóng thành công',
    data: result,
  });
};

exports.verifyOtp = (req, res) => {
  const { userId, action, transactionId, otp } = req.body;

  if (!userId || !otp) {
    return res.status(400).json({
      success: false,
      error: 'VALIDATION_ERROR',
      message: 'userId và mã otp là bắt buộc',
    });
  }

  const result = otpService.verifyOtp({
    userId,
    action: action || 'PAYMENT',
    transactionId,
    otp,
  });

  if (!result.valid) {
    return res.status(400).json({
      success: false,
      error: result.error,
      message: result.message,
      remainingAttempts: result.remainingAttempts,
    });
  }

  return res.json({
    success: true,
    verified: true,
    message: result.message,
    data: result.data,
  });
};
