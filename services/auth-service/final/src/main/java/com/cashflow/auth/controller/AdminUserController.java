package com.cashflow.auth.controller;

import com.cashflow.auth.dto.Requests;
import com.cashflow.auth.dto.UserView;
import com.cashflow.auth.service.UserService;
import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Map;

@RestController
@RequestMapping("/api/admin/users")
public class AdminUserController {
    private final UserService users;

    public AdminUserController(UserService users) {
        this.users = users;
    }

    @GetMapping({"", "/"})
    Map<String, Object> list() {
        List<UserView.Detailed> result = users.list();
        return Map.of("success", true, "count", result.size(), "users", result);
    }

    @GetMapping({"/{id}", "/{id}/"})
    Map<String, Object> get(@PathVariable long id) {
        return Map.of("success", true, "user", users.get(id));
    }

    @PostMapping({"", "/"})
    ResponseEntity<Map<String, Object>> create(@Valid @RequestBody Requests.AdminCreate request) {
        return ResponseEntity.status(HttpStatus.CREATED).body(Map.of(
                "success", true, "message", "User created successfully", "user", users.create(request)));
    }

    @PutMapping({"/{id}", "/{id}/"})
    Map<String, Object> update(@PathVariable long id, @Valid @RequestBody Requests.AdminUpdate request) {
        return Map.of("success", true, "message", "User updated successfully", "user", users.update(id, request));
    }

    @DeleteMapping({"/{id}", "/{id}/"})
    Map<String, Object> delete(@PathVariable long id) {
        users.delete(id);
        return Map.of("success", true, "message", "User deleted successfully");
    }
}
