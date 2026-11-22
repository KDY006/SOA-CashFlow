/**
 * OTP Express Router
 */
const express = require('express');
const router = express.Router();
const controller = require('../controllers/otpController');

// Sinh mã OTP cho giao dịch thanh toán
router.post('/notifications/otp/generate', controller.generateOtp);
router.post('/otp/generate', controller.generateOtp);

// Xác thực mã OTP
router.post('/notifications/otp/verify', controller.verifyOtp);
router.post('/otp/verify', controller.verifyOtp);

module.exports = router;
