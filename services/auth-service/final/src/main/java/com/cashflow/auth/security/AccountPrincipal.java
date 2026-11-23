package com.cashflow.auth.security;

public record AccountPrincipal(long id, String username, String role, String status) {}
