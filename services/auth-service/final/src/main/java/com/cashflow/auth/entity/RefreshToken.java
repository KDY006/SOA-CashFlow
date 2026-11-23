package com.cashflow.auth.entity;

import jakarta.persistence.*;
import org.hibernate.annotations.Generated;

import java.sql.Timestamp;

@Entity
@Table(name = "refresh_tokens")
public class RefreshToken {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @ManyToOne(fetch = FetchType.LAZY, optional = false)
    @JoinColumn(name = "user_id", nullable = false)
    private User user;

    @Column(nullable = false, unique = true, length = 255)
    private String token;

    @Column(name = "expiry_date", nullable = false)
    private Timestamp expiryDate;

    @Generated
    @Column(name = "created_at", insertable = false, updatable = false)
    private Timestamp createdAt;

    protected RefreshToken() {}

    public RefreshToken(User user, String token, Timestamp expiryDate) {
        this.user = user;
        this.token = token;
        this.expiryDate = expiryDate;
    }

    public Long getOwnerId() { return user.getId(); }
    public String getToken() { return token; }
    public Timestamp getExpiryDate() { return expiryDate; }
}
