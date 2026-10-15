<%@ page contentType="text/html;charset=UTF-8" pageEncoding="UTF-8" isErrorPage="true" %>
<%@ taglib prefix="c" uri="jakarta.tags.core" %>
<c:set var="pageTitle" value="Error"/>
<%@ include file="/WEB-INF/views/partials/header.jspf" %>
<c:set var="code" value="${pageContext.errorData.statusCode}"/>
<div class="card">
  <c:choose>
    <c:when test="${code == 404}"><h1>Not found</h1><p>That student or file does not exist, or it is no longer in storage.</p></c:when>
    <c:when test="${code == 400}"><h1>Invalid request</h1><p>The link you followed is not valid.</p></c:when>
    <c:when test="${code == 403}"><h1>Not allowed</h1><p>You do not have permission to view this page.</p></c:when>
    <c:otherwise><h1>Something went wrong</h1><p>The request could not be completed. Check that MySQL is running and that PORTAL_CONFIG points to config/portal.properties, then try again.</p></c:otherwise>
  </c:choose>
  <p><a class="btn" href="${ctx}/portal/students">Back to students</a></p>
</div>
<%@ include file="/WEB-INF/views/partials/footer.jspf" %>
