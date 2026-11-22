package com.cashflow.auth.service;

import com.cashflow.auth.model.User;
import com.cashflow.auth.repository.UserRepository;
import com.cashflow.auth.util.PasswordUtil;

import java.sql.SQLIntegrityConstraintViolationException;
import java.util.List;
import java.util.regex.Pattern;

public class AuthService {

    private static final Pattern USERNAME_PATTERN =
            Pattern.compile("^[A-Za-z0-9._]{3,50}$");

    private static final Pattern EMAIL_PATTERN =
            Pattern.compile("^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$");

    private static final Pattern PHONE_PATTERN =
            Pattern.compile("^\\+?[0-9]{9,15}$");

    private final UserRepository userRepository;

    public AuthService() {
        this.userRepository = new UserRepository();
    }

    // =========================================================
    // REGISTER - USER TU DANG KY
    // =========================================================
    public User register(
            String username,
            String email,
            String password,
            String fullName,
            String phoneNumber
    ) throws Exception {

        validateUsername(username);

        email = validateAndNormalizeEmail(email);
        validatePassword(password);

        fullName = normalizeFullName(fullName);
        phoneNumber = normalizePhoneNumber(phoneNumber);

        if (userRepository.existsByUsername(username)) {
            throw new IllegalStateException("Username already exists");
        }

        if (userRepository.existsByEmail(email)) {
            throw new IllegalStateException("Email already exists");
        }

        User user = new User();

        user.setUsername(username);
        user.setEmail(email);
        user.setPasswordHash(
                PasswordUtil.hashPassword(password)
        );
        user.setFullName(fullName);
        user.setPhoneNumber(phoneNumber);

        // User tu dang ky khong duoc tu cap quyen ADMIN
        user.setRole("USER");
        user.setStatus("ACTIVE");

        long id;

        try {
            id = userRepository.create(user);
        } catch (SQLIntegrityConstraintViolationException e) {
            throw new IllegalStateException(
                    "Username or email already exists"
            );
        }

        user.setId(id);

        return user;
    }

    // =========================================================
    // LOGIN
    // Cho phep dang nhap bang username hoac email
    // =========================================================
    public User login(
            String username,
            String password
    ) throws Exception {

        if (username == null || username.isBlank()) {
            throw new IllegalArgumentException(
                    "Username is required"
            );
        }

        if (password == null || password.isBlank()) {
            throw new IllegalArgumentException(
                    "Password is required"
            );
        }

        User user =
                userRepository.findByUsername(username);

        if (user == null) {
            user = userRepository.findByEmail(
                    username.toLowerCase()
            );
        }

        if (user == null) {
            throw new IllegalArgumentException(
                    "Invalid username or password"
            );
        }

        if (!PasswordUtil.verifyPassword(
                password,
                user.getPasswordHash()
        )) {
            throw new IllegalArgumentException(
                    "Invalid username or password"
            );
        }

        if (!"ACTIVE".equalsIgnoreCase(user.getStatus())) {
            throw new IllegalArgumentException(
                    "Account is not active"
            );
        }

        return user;
    }

    // =========================================================
    // ADMIN - READ ALL USERS
    // =========================================================
    public List<User> getAllUsers() throws Exception {
        return userRepository.findAll();
    }

    // =========================================================
    // ADMIN - READ ONE USER
    // =========================================================
    public User getUserById(long id) throws Exception {

        if (id <= 0) {
            throw new IllegalArgumentException(
                    "Invalid user id"
            );
        }

        User user = userRepository.findById(id);

        if (user == null) {
            throw new IllegalArgumentException(
                    "User not found"
            );
        }

        return user;
    }

    // =========================================================
    // ADMIN - CREATE USER
    // Admin co the tao USER hoac ADMIN
    // =========================================================
    public User createUserByAdmin(
            String username,
            String email,
            String password,
            String fullName,
            String phoneNumber,
            String role,
            String status
    ) throws Exception {

        validateUsername(username);

        email = validateAndNormalizeEmail(email);
        validatePassword(password);

        fullName = normalizeFullName(fullName);
        phoneNumber = normalizePhoneNumber(phoneNumber);

        role = normalizeRole(role);
        status = normalizeStatus(status);

        if (userRepository.existsByUsername(username)) {
            throw new IllegalStateException(
                    "Username already exists"
            );
        }

        if (userRepository.existsByEmail(email)) {
            throw new IllegalStateException(
                    "Email already exists"
            );
        }

        User user = new User();

        user.setUsername(username);
        user.setEmail(email);
        user.setPasswordHash(
                PasswordUtil.hashPassword(password)
        );
        user.setFullName(fullName);
        user.setPhoneNumber(phoneNumber);
        user.setRole(role);
        user.setStatus(status);

        long id;

        try {
            id = userRepository.create(user);
        } catch (SQLIntegrityConstraintViolationException e) {
            throw new IllegalStateException(
                    "Username or email already exists"
            );
        }

        user.setId(id);

        return user;
    }

    // =========================================================
    // ADMIN - UPDATE USER
    // Khong thay password o method nay
    // =========================================================
    public User updateUserByAdmin(
            long id,
            String username,
            String email,
            String fullName,
            String phoneNumber,
            String role,
            String status
    ) throws Exception {

        if (id <= 0) {
            throw new IllegalArgumentException(
                    "Invalid user id"
            );
        }

        User existingUser = userRepository.findById(id);

        if (existingUser == null) {
            throw new IllegalArgumentException(
                    "User not found"
            );
        }

        validateUsername(username);

        email = validateAndNormalizeEmail(email);

        fullName = normalizeFullName(fullName);
        phoneNumber = normalizePhoneNumber(phoneNumber);

        role = normalizeRole(role);
        status = normalizeStatus(status);

        User sameUsername =
                userRepository.findByUsername(username);

        if (sameUsername != null &&
                sameUsername.getId() != id) {
            throw new IllegalStateException(
                    "Username already exists"
            );
        }

        User sameEmail =
                userRepository.findByEmail(email);

        if (sameEmail != null &&
                sameEmail.getId() != id) {
            throw new IllegalStateException(
                    "Email already exists"
            );
        }

        existingUser.setUsername(username);
        existingUser.setEmail(email);
        existingUser.setFullName(fullName);
        existingUser.setPhoneNumber(phoneNumber);
        existingUser.setRole(role);
        existingUser.setStatus(status);

        boolean updated =
                userRepository.update(existingUser);

        if (!updated) {
            throw new IllegalStateException(
                    "Cannot update user"
            );
        }

        return userRepository.findById(id);
    }

    // =========================================================
    // ADMIN - DELETE USER
    // =========================================================
    public void deleteUserByAdmin(long id) throws Exception {

        if (id <= 0) {
            throw new IllegalArgumentException(
                    "Invalid user id"
            );
        }

        User user = userRepository.findById(id);

        if (user == null) {
            throw new IllegalArgumentException(
                    "User not found"
            );
        }

        boolean deleted =
                userRepository.deleteById(id);

        if (!deleted) {
            throw new IllegalStateException(
                    "Cannot delete user"
            );
        }
    }

    // =========================================================
    // USER - UPDATE OWN PROFILE
    // User chi duoc sua thong tin ca nhan
    // Khong duoc sua role/status
    // =========================================================
    public User updateProfile(
            long userId,
            String fullName,
            String phoneNumber
    ) throws Exception {

        User user = userRepository.findById(userId);

        if (user == null) {
            throw new IllegalArgumentException(
                    "User not found"
            );
        }

        fullName = normalizeFullName(fullName);
        phoneNumber = normalizePhoneNumber(phoneNumber);

        user.setFullName(fullName);
        user.setPhoneNumber(phoneNumber);

        boolean updated = userRepository.update(user);

        if (!updated) {
            throw new IllegalStateException(
                    "Cannot update profile"
            );
        }

        return userRepository.findById(userId);
    }

        // =========================================================
    // USER - CHANGE OWN PASSWORD
    // User must provide the correct current password
    // =========================================================
    public void changePassword(
            long userId,
            String currentPassword,
            String newPassword
    ) throws Exception {

        if (userId <= 0) {
            throw new IllegalArgumentException(
                    "Invalid user id"
            );
        }

        if (currentPassword == null ||
                currentPassword.isBlank()) {
            throw new IllegalArgumentException(
                    "Current password is required"
            );
        }

        if (newPassword == null ||
                newPassword.isBlank()) {
            throw new IllegalArgumentException(
                    "New password is required"
            );
        }

        User user = userRepository.findById(userId);

        if (user == null) {
            throw new IllegalArgumentException(
                    "User not found"
            );
        }

        if (!"ACTIVE".equalsIgnoreCase(user.getStatus())) {
            throw new IllegalArgumentException(
                    "Account is not active"
            );
        }

        // Verify current password
        if (!PasswordUtil.verifyPassword(
                currentPassword,
                user.getPasswordHash()
        )) {
            throw new IllegalArgumentException(
                    "Current password is incorrect"
            );
        }

        // Apply the same password rules used by register
        validatePassword(newPassword);

        // Do not allow changing to the same password
        if (PasswordUtil.verifyPassword(
                newPassword,
                user.getPasswordHash()
        )) {
            throw new IllegalArgumentException(
                    "New password must be different from current password"
            );
        }

        String newPasswordHash =
                PasswordUtil.hashPassword(newPassword);

        boolean updated =
                userRepository.updatePassword(
                        userId,
                        newPasswordHash
                );

        if (!updated) {
            throw new IllegalStateException(
                    "Cannot update password"
            );
        }
    }

    // =========================================================
    // VALIDATION METHODS
    // =========================================================

    private void validateUsername(String username) {

        if (username == null || username.isBlank()) {
            throw new IllegalArgumentException(
                    "Username is required"
            );
        }

        if (!USERNAME_PATTERN.matcher(username).matches()) {
            throw new IllegalArgumentException(
                    "Username must be 3-50 characters " +
                    "(letters, digits, '.', '_')"
            );
        }
    }

    private String validateAndNormalizeEmail(String email) {

        if (email == null || email.isBlank()) {
            throw new IllegalArgumentException(
                    "Email is required"
            );
        }

        email = email.toLowerCase();

        if (email.length() > 100 ||
                !EMAIL_PATTERN.matcher(email).matches()) {
            throw new IllegalArgumentException(
                    "Email is invalid"
            );
        }

        return email;
    }

    private void validatePassword(String password) {

        if (password == null || password.length() < 6) {
            throw new IllegalArgumentException(
                    "Password must contain at least 6 characters"
            );
        }

        if (password.length() > 100) {
            throw new IllegalArgumentException(
                    "Password must not exceed 100 characters"
            );
        }
    }

    private String normalizeFullName(String fullName) {

        if (fullName != null && fullName.isBlank()) {
            fullName = null;
        }

        if (fullName != null && fullName.length() > 100) {
            throw new IllegalArgumentException(
                    "Full name must not exceed 100 characters"
            );
        }

        return fullName;
    }

    private String normalizePhoneNumber(String phoneNumber) {

        if (phoneNumber != null && phoneNumber.isBlank()) {
            phoneNumber = null;
        }

        if (phoneNumber != null &&
                !PHONE_PATTERN.matcher(phoneNumber).matches()) {
            throw new IllegalArgumentException(
                    "Phone number is invalid"
            );
        }

        return phoneNumber;
    }

    private String normalizeRole(String role) {

        if (role == null || role.isBlank()) {
            return "USER";
        }

        role = role.toUpperCase();

        if (!role.equals("USER") &&
                !role.equals("ADMIN")) {
            throw new IllegalArgumentException(
                    "Role must be USER or ADMIN"
            );
        }

        return role;
    }

    private String normalizeStatus(String status) {

        if (status == null || status.isBlank()) {
            return "ACTIVE";
        }

        status = status.toUpperCase();

        if (!status.equals("ACTIVE") &&
                !status.equals("BLOCKED")) {
            throw new IllegalArgumentException(
                    "Status must be ACTIVE or BLOCKED"
            );
        }

        return status;
    }
}