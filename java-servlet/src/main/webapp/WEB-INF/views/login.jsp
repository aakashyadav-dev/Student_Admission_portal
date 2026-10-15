<%@ page contentType="text/html;charset=UTF-8" pageEncoding="UTF-8" %>
<%@ taglib prefix="c" uri="jakarta.tags.core" %>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in | Student Admission Portal</title>
  <link rel="icon" href="${ctx}/assets/img/logo.svg" type="image/svg+xml">
  <link rel="stylesheet" href="${ctx}/assets/css/portal.css">
</head>
<body>
<main class="auth-wrap">
  <div class="card auth-card">
    <div class="brand"><img class="brand-mark" src="${ctx}/assets/img/logo.svg" alt=""> Student Admission Portal</div>
    <h1>Sign in to student profiles</h1>
    <p class="muted">Staff accounts only. This sign-in is separate from the PHP admin panel.</p>

    <c:if test="${signedOut}"><div class="alert alert-ok" role="status">You have been signed out.</div></c:if>
    <c:if test="${not empty error}"><div class="alert alert-err" role="alert"><c:out value="${error}"/></div></c:if>

    <form method="post" action="${ctx}/login" novalidate>
      <input type="hidden" name="csrf_token" value="<c:out value='${csrfToken}'/>">
      <c:if test="${not empty next}"><input type="hidden" name="next" value="<c:out value='${next}'/>"></c:if>
      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" autocomplete="username" required autofocus value="<c:out value='${param.username}'/>">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
      </div>
      <button class="btn" type="submit">Sign in</button>
    </form>
  </div>
</main>
</body>
</html>
