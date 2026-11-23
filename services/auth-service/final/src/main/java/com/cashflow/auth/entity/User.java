package com.cashflow.auth.entity;

import jakarta.persistence.*;
import org.hibernate.annotations.Generated;
import org.hibernate.generator.EventType;

import java.sql.Timestamp;

@Entity
@Table(name = "users")
public class User {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false, unique = true, length = 50)
    private String username;

    @Column(nullable = false, unique = true, length = 100)
    private String email;

    @Column(name = "password_hash", nullable = false, length = 255)
    private String passwordHash;

    @Column(name = "full_name", length = 100)
    private String fullName;

    @Column(name = "phone_number", length = 20)
    private String phoneNumber;

    @Column(nullable = false, length = 20)
    private String role;

    @Column(nullable = false, length = 20)
    private String status;

    @Generated
    @Column(name = "created_at", insertable = false, updatable = false)
    private Timestamp createdAt;

    @Generated(event = {EventType.INSERT, EventType.UPDATE})
    @Column(name = "updated_at", insertable = false, updatable = false)
    private Timestamp updatedAt;

    protected User() {}

    public User(String username, String email, String passwordHash, String fullName,
                String phoneNumber, String role, String status) {
        this.passwordHash = passwordHash;
        update(username, email, fullName, phoneNumber, role, status);
    }

    public void update(String username, String email, String fullName, String phoneNumber,
                       String role, String status) {
        this.username = username;
        this.email = email;
        this.role = role;
        this.status = status;
        updateProfile(fullName, phoneNumber);
    }

    public void updateProfile(String fullName, String phoneNumber) {
        this.fullName = fullName;
        this.phoneNumber = phoneNumber;
    }

    public void changePassword(String passwordHash) {
        this.passwordHash = passwordHash;
    }

    public Long getId() { return id; }
    public String getUsername() { return username; }
    public String getEmail() { return email; }
    public String getPasswordHash() { return passwordHash; }
    public String getFullName() { return fullName; }
    public String getPhoneNumber() { return phoneNumber; }
    public String getRole() { return role; }
    public String getStatus() { return status; }
    public Timestamp getCreatedAt() { return createdAt; }
    public Timestamp getUpdatedAt() { return updatedAt; }
    public boolean isActive() { return "ACTIVE".equalsIgnoreCase(status); }
}
