package com.cashflow.auth.exception;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.dao.DataIntegrityViolationException;
import org.springframework.dao.TransientDataAccessException;
import org.springframework.dao.DataAccessResourceFailureException;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.http.converter.HttpMessageNotReadableException;
import org.springframework.transaction.CannotCreateTransactionException;
import org.springframework.web.ErrorResponse;
import org.springframework.web.bind.MethodArgumentNotValidException;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.RestControllerAdvice;
import org.springframework.web.method.annotation.MethodArgumentTypeMismatchException;

import java.sql.SQLException;

@RestControllerAdvice
public class ApiErrors {
    private static final Logger log = LoggerFactory.getLogger(ApiErrors.class);

    public record Failure(boolean success, String message) {
        public Failure(String message) { this(false, message); }
    }

    @ExceptionHandler(ApiException.class)
    ResponseEntity<Failure> application(ApiException ex) {
        return response(ex.status(), ex.getMessage());
    }

    @ExceptionHandler(MethodArgumentNotValidException.class)
    ResponseEntity<Failure> validation(MethodArgumentNotValidException ex) {
        var field = ex.getBindingResult().getFieldError();
        String message = field == null ? "Invalid request" : field.getField() + ": " + field.getDefaultMessage();
        if (field != null && "refreshToken".equals(field.getField()) && "NotBlank".equals(field.getCode())) {
            message = "Refresh token is required";
        }
        return response(HttpStatus.BAD_REQUEST, message);
    }

    @ExceptionHandler(HttpMessageNotReadableException.class)
    ResponseEntity<Failure> malformed() {
        return response(HttpStatus.BAD_REQUEST, "Request body must be a valid JSON object");
    }

    @ExceptionHandler(MethodArgumentTypeMismatchException.class)
    ResponseEntity<Failure> argument() {
        return response(HttpStatus.BAD_REQUEST, "Invalid user id");
    }

    @ExceptionHandler(DataIntegrityViolationException.class)
    ResponseEntity<Failure> integrity(DataIntegrityViolationException ex) {
        for (Throwable cause = ex; cause != null; cause = cause.getCause()) {
            if (cause instanceof SQLException sql && (sql.getErrorCode() == 1062 || "23505".equals(sql.getSQLState()))) {
                return response(HttpStatus.CONFLICT, "Username or email already exists");
            }
        }
        return response(HttpStatus.CONFLICT, "Database constraint violation");
    }

    @ExceptionHandler({DataAccessResourceFailureException.class, TransientDataAccessException.class,
            CannotCreateTransactionException.class})
    ResponseEntity<Failure> unavailable() {
        return response(HttpStatus.SERVICE_UNAVAILABLE, "Database temporarily unavailable");
    }

    @ExceptionHandler(Exception.class)
    ResponseEntity<Failure> unexpected(Exception ex) {
        if (ex instanceof ErrorResponse error) {
            return ResponseEntity.status(error.getStatusCode()).headers(error.getHeaders())
                    .body(new Failure(error.getStatusCode().value() == 405
                            ? "Method not allowed" : error.getStatusCode().toString()));
        }
        log.error("Request failed ({})", ex.getClass().getSimpleName());
        return response(HttpStatus.INTERNAL_SERVER_ERROR, "Internal server error");
    }

    private ResponseEntity<Failure> response(HttpStatus status, String message) {
        return ResponseEntity.status(status).body(new Failure(message));
    }
}
