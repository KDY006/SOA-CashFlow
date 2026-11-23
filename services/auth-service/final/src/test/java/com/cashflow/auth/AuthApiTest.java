package com.cashflow.auth;

import com.cashflow.auth.entity.RefreshToken;
import com.cashflow.auth.entity.User;
import com.cashflow.auth.repository.RefreshTokenRepository;
import com.cashflow.auth.repository.UserRepository;
import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.nimbusds.jose.jwk.source.ImmutableSecret;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.ValueSource;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.autoconfigure.web.servlet.AutoConfigureMockMvc;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.dao.DataIntegrityViolationException;
import org.springframework.http.MediaType;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.security.oauth2.jose.jws.MacAlgorithm;
import org.springframework.security.oauth2.jwt.*;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.test.web.servlet.ResultActions;
import org.springframework.test.web.servlet.request.MockHttpServletRequestBuilder;

import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.sql.Timestamp;
import java.time.Instant;
import java.util.Base64;

import static org.assertj.core.api.Assertions.*;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

@SpringBootTest(properties = {
        "JWT_SECRET=test-signing-key-for-auth-integration-only",
        "spring.datasource.url=jdbc:h2:mem:auth_test;MODE=MySQL;DB_CLOSE_DELAY=-1",
        "spring.datasource.driver-class-name=org.h2.Driver",
        "spring.datasource.username=sa",
        "spring.datasource.password=",
        "spring.sql.init.mode=always",
        "spring.sql.init.schema-locations=classpath:auth-test-schema.sql"
})
@AutoConfigureMockMvc
@ActiveProfiles("test")
class AuthApiTest {
    static final String SEED_HASH =
            "210000:aYagp9baQD0LZ9O/sVLxPQ==:3+Pmnd0JLerckR8Jj1a2iHsMw/3xz4TllUi4Z5NsU9g=";

    @Autowired MockMvc mvc;
    @Autowired ObjectMapper json;
    @Autowired UserRepository users;
    @Autowired RefreshTokenRepository refreshTokens;
    @Autowired JwtEncoder encoder;
    @Autowired JwtDecoder decoder;
    @Autowired JdbcTemplate jdbc;

    User admin;
    User member;

    @BeforeEach
    void seed() {
        refreshTokens.deleteAllInBatch();
        users.deleteAllInBatch();
        admin = seedUser("admin", "ADMIN", "ACTIVE");
        member = seedUser("member1", "USER", "ACTIVE");
    }

    @Test
    void registersWithLegacyAliasesAndSafeDefaults() throws Exception {
        mvc.perform(body(post("/api/auth/register"), """
                {"username":" new.user ","email":"NEW@cashflow.local","password":"simple123",
                 "full_name":"Tên mới","phone_number":"+84901234567","role":"ADMIN","status":"BLOCKED"}
                """)).andExpect(status().isCreated())
                .andExpect(jsonPath("$.user.email").value("new@cashflow.local"))
                .andExpect(jsonPath("$.user.fullName").value("Tên mới"))
                .andExpect(jsonPath("$.user.phoneNumber").value("+84901234567"))
                .andExpect(jsonPath("$.user.role").value("USER"))
                .andExpect(jsonPath("$.user.status").value("ACTIVE"))
                .andExpect(jsonPath("$.user.passwordHash").doesNotExist())
                .andExpect(jsonPath("$.user.createdAt").doesNotExist());
        assertThat(users.findByUsername("new.user").orElseThrow().getPasswordHash())
                .startsWith("210000:").doesNotContain("simple123");
    }

    @Test
    void duplicateUsernameAndEmailReturnConflict() throws Exception {
        mvc.perform(body(post("/api/auth/register"), """
                {"username":"member1","email":"new@cashflow.local","password":"simple123"}
                """)).andExpect(status().isConflict()).andExpect(jsonPath("$.message").value("Username already exists"));
        mvc.perform(body(post("/api/auth/register"), """
                {"username":"another","email":"MEMBER1@cashflow.local","password":"simple123"}
                """)).andExpect(status().isConflict()).andExpect(jsonPath("$.message").value("Email already exists"));
    }

    @ParameterizedTest
    @ValueSource(strings = {"null", "[1]", "{broken", "{}", """
            {"username":"validName","email":"bad","password":"short","phoneNumber":"invalid"}
            """})
    void invalidRequestsReturnSafeJson(String request) throws Exception {
        mvc.perform(body(post("/api/auth/register"), request)).andExpect(status().isBadRequest())
                .andExpect(jsonPath("$.success").value(false));
    }

    @Test
    void seedsCanLoginByUsernameAndEmailAndValidate() throws Exception {
        String accessToken = login("admin").get("accessToken").asText();
        mvc.perform(bearer(get("/api/auth/validate"), accessToken)).andExpect(status().isOk())
                .andExpect(jsonPath("$.valid").value(true))
                .andExpect(jsonPath("$.userId").value(admin.getId()))
                .andExpect(jsonPath("$.role").value("ADMIN"));
        JsonNode credentials = login("MEMBER1@CASHFLOW.LOCAL");
        assertThat(credentials.get("user").get("username").asText()).isEqualTo("member1");
        assertThat(credentials.get("user").has("passwordHash")).isFalse();
        assertThat(credentials.get("expiresIn").asInt()).isEqualTo(3600);
        Jwt jwt = decoder.decode(accessToken);
        assertThat(jwt.getHeaders()).containsEntry("alg", "HS256");
        assertThat(jwt.getExpiresAt()).isEqualTo(jwt.getIssuedAt().plusSeconds(3600));
        assertThat(jwt.getClaims().keySet()).containsExactlyInAnyOrder("userId", "username", "role", "iat", "exp");
    }

    @Test
    void wrongPasswordAndBlockedAccountAreRejected() throws Exception {
        mvc.perform(body(post("/api/auth/login"), """
                {"username":"member1","password":"incorrect"}
                """)).andExpect(status().isUnauthorized())
                .andExpect(jsonPath("$.message").value("Invalid username or password"));
        seedUser("blocked", "USER", "BLOCKED");
        loginRequest("blocked", "password123").andExpect(status().isUnauthorized())
                .andExpect(jsonPath("$.message").value("Account is not active"));
    }

    @Test
    void rejectsExpiredWronglySignedAndTamperedTokens() throws Exception {
        var wrongKey = new SecretKeySpec("unrelated-signing-key-for-auth-test-only".getBytes(StandardCharsets.UTF_8),
                "HmacSHA256");
        String expired = issue(encoder, Instant.now().minusSeconds(10));
        String wrongSignature = issue(new NimbusJwtEncoder(new ImmutableSecret<>(wrongKey)), Instant.now().plusSeconds(60));
        String[] pieces = login("member1").get("accessToken").asText().split("\\.");
        String payload = new String(Base64.getUrlDecoder().decode(pieces[1]), StandardCharsets.UTF_8)
                .replace("USER", "ADMIN");
        pieces[1] = Base64.getUrlEncoder().withoutPadding().encodeToString(payload.getBytes(StandardCharsets.UTF_8));

        for (String token : new String[]{expired, wrongSignature, String.join(".", pieces)}) {
            mvc.perform(bearer(get("/api/auth/validate"), token)).andExpect(status().isUnauthorized());
        }
        mvc.perform(get("/api/profile").header("X-User-Id", member.getId()))
                .andExpect(status().isUnauthorized()).andExpect(header().string("WWW-Authenticate", "Bearer"));
    }

    @Test
    void refreshSupportsExistingAndHashedTokensAndChecksExpiry() throws Exception {
        String legacyToken = "A".repeat(64);
        refreshTokens.save(new RefreshToken(member, legacyToken, Timestamp.from(Instant.now().plusSeconds(120))));
        refresh(legacyToken).andExpect(status().isOk());
        refresh("a".repeat(64)).andExpect(status().isUnauthorized());
        String token = login("member1").get("refreshToken").asText();
        assertThat(refreshTokens.findAll()).singleElement()
                .satisfies(stored -> assertThat(stored.getToken()).startsWith("sha256:").doesNotContain(token));
        refresh(legacyToken).andExpect(status().isUnauthorized());
        refresh(token).andExpect(status().isOk()).andExpect(jsonPath("$.accessToken").isString())
                .andExpect(jsonPath("$.refreshToken").doesNotExist());
        refresh(token).andExpect(status().isOk());
        refresh("missing").andExpect(status().isUnauthorized());
        refreshTokens.save(new RefreshToken(member, legacyToken, Timestamp.from(Instant.now().minusSeconds(120))));
        refresh(legacyToken).andExpect(status().isUnauthorized());
    }

    @Test
    void readsAndUpdatesOnlyOwnProfileAndChangesPassword() throws Exception {
        JsonNode credentials = login("member1");
        String token = credentials.get("accessToken").asText();
        mvc.perform(bearer(get("/api/profile"), token)).andExpect(status().isOk())
                .andExpect(jsonPath("$.profile.id").value(member.getId()))
                .andExpect(jsonPath("$.profile.createdAt").isNumber());
        mvc.perform(bearer(body(put("/api/profile"), """
                {"fullName":"Updated name","phoneNumber":"0901234567","role":"ADMIN","status":"BLOCKED","id":1}
                """), token)).andExpect(status().isOk())
                .andExpect(jsonPath("$.profile.fullName").value("Updated name"))
                .andExpect(jsonPath("$.profile.role").value("USER"))
                .andExpect(jsonPath("$.profile.status").value("ACTIVE"));
        mvc.perform(bearer(body(put("/api/profile/password"), """
                {"currentPassword":"wrong","newPassword":" changed123 "}
                """), token)).andExpect(status().isBadRequest());
        mvc.perform(bearer(body(put("/api/profile/password"), """
                {"currentPassword":"password123","newPassword":" changed123 "}
                """), token)).andExpect(status().isOk());
        refresh(credentials.get("refreshToken").asText()).andExpect(status().isUnauthorized());
        loginRequest("member1", "password123").andExpect(status().isUnauthorized());
        loginRequest("member1", " changed123 ").andExpect(status().isOk());
    }

    @Test
    void userCannotUseAdminRoutesAndAdminCanCompleteCrud() throws Exception {
        String userToken = login("member1").get("accessToken").asText();
        mvc.perform(bearer(get("/api/admin/users"), userToken).header("X-User-Role", "ADMIN"))
                .andExpect(status().isForbidden());
        String adminToken = login("admin").get("accessToken").asText();
        mvc.perform(bearer(get("/api/admin/users"), adminToken)).andExpect(status().isOk())
                .andExpect(jsonPath("$.count").value(2))
                .andExpect(jsonPath("$.users[0].username").value("admin"));
        ResultActions created = mvc.perform(bearer(body(post("/api/admin/users"), """
                {"username":"created","email":"created@cashflow.local","password":"simple123","role":"user"}
                """), adminToken)).andExpect(status().isCreated())
                .andExpect(jsonPath("$.user.createdAt").isNumber());
        long id = result(created).at("/user/id").asLong();
        String path = "/api/admin/users/" + id;
        mvc.perform(bearer(get(path), adminToken)).andExpect(status().isOk())
                .andExpect(jsonPath("$.user.username").value("created"));
        mvc.perform(bearer(body(put(path), """
                {"username":"created","email":"created@cashflow.local","role":"ROOT"}
                """), adminToken)).andExpect(status().isBadRequest());
        mvc.perform(bearer(body(put(path), """
                {"username":"created","email":"created@cashflow.local","role":"admin","status":"active"}
                """), adminToken)).andExpect(status().isOk()).andExpect(jsonPath("$.user.role").value("ADMIN"));
        String refresh = loginRequest("created", "simple123").andExpect(status().isOk()).andReturn()
                .getResponse().getContentAsString();
        mvc.perform(bearer(delete(path), adminToken)).andExpect(status().isOk());
        refresh(json.readTree(refresh).get("refreshToken").asText()).andExpect(status().isUnauthorized());
        mvc.perform(bearer(get(path), adminToken)).andExpect(status().isBadRequest()).andExpect(jsonPath("$.message").value("User not found"));
        mvc.perform(bearer(get("/api/admin/users/invalid"), adminToken)).andExpect(status().isBadRequest());
    }

    @Test
    void storedRoleAndStatusChangesAffectPreviouslyIssuedTokens() throws Exception {
        String adminToken = login("admin").get("accessToken").asText();
        JsonNode memberCredentials = login("member1");
        String memberToken = memberCredentials.get("accessToken").asText();
        String memberPath = "/api/admin/users/" + member.getId();
        mvc.perform(bearer(body(put(memberPath), """
                {"username":"member1","email":"member1@cashflow.local","role":"ADMIN"}
                """), adminToken)).andExpect(status().isOk());
        mvc.perform(bearer(get("/api/admin/users"), memberToken)).andExpect(status().isUnauthorized());

        String elevated = result(refresh(memberCredentials.get("refreshToken").asText()).andExpect(status().isOk()))
                .get("accessToken").asText();
        mvc.perform(bearer(get("/api/admin/users"), elevated)).andExpect(status().isOk());
        mvc.perform(bearer(body(put(memberPath), """
                {"username":"member1","email":"member1@cashflow.local","role":"ADMIN","status":"BLOCKED"}
                """), adminToken)).andExpect(status().isOk());
        mvc.perform(bearer(get("/api/profile"), elevated)).andExpect(status().isUnauthorized());
        refresh(memberCredentials.get("refreshToken").asText()).andExpect(status().isUnauthorized());
    }

    @Test
    void databaseEnforcesUniqueConstraints() {
        assertThatThrownBy(() -> seedUser("member1", "ADMIN", "ACTIVE"))
                .isInstanceOf(DataIntegrityViolationException.class);
        refreshTokens.saveAndFlush(new RefreshToken(member, "unique", Timestamp.from(Instant.now().plusSeconds(60))));
        assertThatThrownBy(() -> refreshTokens.saveAndFlush(
                new RefreshToken(member, "unique", Timestamp.from(Instant.now().plusSeconds(60)))))
                .isInstanceOf(DataIntegrityViolationException.class);
    }

    @Test
    void rejectsRefreshTokenWithMissingOwnerAtDatabaseLevel() {
        assertThatThrownBy(() -> jdbc.update(
                "INSERT INTO refresh_tokens (user_id, token, expiry_date) VALUES (?, ?, ?)",
                Long.MAX_VALUE, "orphan", Timestamp.from(Instant.now().plusSeconds(60))))
                .isInstanceOf(DataIntegrityViolationException.class);
    }

    private User seedUser(String username, String role, String status) {
        return users.saveAndFlush(new User(username, username + "@cashflow.local", SEED_HASH, null, null, role, status));
    }

    private JsonNode login(String username) throws Exception {
        return result(loginRequest(username, "password123").andExpect(status().isOk()));
    }

    private ResultActions loginRequest(String username, String password) throws Exception {
        return mvc.perform(body(post("/api/auth/login"),
                json.writeValueAsString(java.util.Map.of("username", username, "password", password))));
    }

    private ResultActions refresh(String token) throws Exception {
        return mvc.perform(body(post("/api/auth/refresh"), "{\"refresh_token\":\"" + token + "\"}"));
    }

    private String issue(JwtEncoder signer, Instant expiry) {
        var claims = JwtClaimsSet.builder().issuedAt(expiry.minusSeconds(3600)).expiresAt(expiry)
                .claim("userId", member.getId()).claim("username", "member1").claim("role", "USER").build();
        return signer.encode(JwtEncoderParameters.from(JwsHeader.with(MacAlgorithm.HS256).build(), claims))
                .getTokenValue();
    }

    private JsonNode result(ResultActions actions) throws Exception {
        return json.readTree(actions.andReturn().getResponse().getContentAsByteArray());
    }

    private MockHttpServletRequestBuilder bearer(MockHttpServletRequestBuilder request, String token) {
        return request.header("Authorization", "Bearer " + token);
    }

    private MockHttpServletRequestBuilder body(MockHttpServletRequestBuilder request, String body) {
        return request.contentType(MediaType.APPLICATION_JSON).content(body);
    }
}
