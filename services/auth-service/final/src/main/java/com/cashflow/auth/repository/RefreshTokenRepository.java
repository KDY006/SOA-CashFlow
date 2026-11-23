package com.cashflow.auth.repository;

import com.cashflow.auth.entity.RefreshToken;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Modifying;
import org.springframework.data.jpa.repository.Query;

import java.util.Optional;

public interface RefreshTokenRepository extends JpaRepository<RefreshToken, Long> {
    @Query("select t.user.id from RefreshToken t where t.token = :value")
    Optional<Long> findOwnerId(String value);

    Optional<RefreshToken> findByToken(String token);

    @Modifying
    @Query("delete from RefreshToken t where t.user.id = :userId")
    void revokeForUser(long userId);
}
