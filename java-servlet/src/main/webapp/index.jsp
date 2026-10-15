<%@ page contentType="text/html;charset=UTF-8" pageEncoding="UTF-8" %>
<%@ taglib prefix="c" uri="jakarta.tags.core" %>
<%-- Welcome page: send everybody to the student list (the AuthFilter redirects to /login when needed). --%>
<c:redirect url="/portal/students"/>
