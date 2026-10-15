<%@ page contentType="text/html;charset=UTF-8" pageEncoding="UTF-8" %>
<%@ taglib prefix="c" uri="jakarta.tags.core" %>
<c:set var="pageTitle" value="${student.fullName}"/>
<%@ include file="/WEB-INF/views/partials/header.jspf" %>

<div class="page-head">
  <div><h1>Student profile</h1><p>Record #<c:out value="${student.id}"/></p></div>
  <a class="btn btn-ghost" href="${ctx}/portal/students">All students</a>
</div>

<section class="card">
  <div class="profile-head">
    <c:choose>
      <c:when test="${not empty profileFile}">
        <%-- The image is streamed by FileServlet, not read from a public folder --%>
        <img class="avatar" src="${ctx}/portal/file?id=${profileFile.id}" alt="Profile photo of <c:out value='${student.fullName}'/>" width="150" height="150">
      </c:when>
      <c:otherwise><div class="avatar-fallback" aria-hidden="true"><c:out value="${student.initials}"/></div></c:otherwise>
    </c:choose>
    <div>
      <h2><c:out value="${student.fullName}"/></h2>
      <p class="muted"><c:out value="${student.rollNumber}"/> &middot; <c:out value="${student.courseCode}"/> &middot; Year <c:out value="${student.yearOfStudy}"/></p>
      <span class="badge badge-${student.status}"><c:out value="${student.status}"/></span>
    </div>
  </div>
  <hr class="rule">
  <dl class="dl">
    <dt>Email</dt><dd><c:out value="${student.email}"/></dd>
    <dt>Phone</dt><dd><c:choose><c:when test="${not empty student.phone}"><c:out value="${student.phone}"/></c:when><c:otherwise><span class="muted">Not provided</span></c:otherwise></c:choose></dd>
    <dt>Course</dt><dd><c:out value="${student.courseName}"/></dd>
    <dt>Date of birth</dt><dd><c:choose><c:when test="${not empty student.dateOfBirth}"><c:out value="${student.dateOfBirth}"/></c:when><c:otherwise><span class="muted">Not provided</span></c:otherwise></c:choose></dd>
    <dt>Gender</dt><dd><c:choose><c:when test="${not empty student.gender}"><c:out value="${student.gender}"/></c:when><c:otherwise><span class="muted">Not provided</span></c:otherwise></c:choose></dd>
    <dt>Address</dt><dd><c:choose><c:when test="${not empty student.address}"><c:out value="${student.address}"/></c:when><c:otherwise><span class="muted">Not provided</span></c:otherwise></c:choose></dd>
    <dt>Registered</dt><dd><c:out value="${student.createdAt}"/></dd>
  </dl>
</section>

<section class="card" aria-labelledby="files-h">
  <h2 id="files-h">Uploaded files (<c:out value="${files.size()}"/>)</h2>
  <c:choose>
    <c:when test="${empty files}">
      <div class="empty"><h3>No files uploaded</h3><p>Documents added in the admin panel appear here.</p></div>
    </c:when>
    <c:otherwise>
      <ul class="file-list">
        <%-- Loop over every file; images get a preview, documents a download link --%>
        <c:forEach var="f" items="${files}">
          <li>
            <c:choose>
              <c:when test="${f.image and f.available}">
                <a href="${ctx}/portal/file?id=${f.id}"><img src="${ctx}/portal/file?id=${f.id}" alt="<c:out value='${f.categoryLabel}'/>" width="64" height="64" loading="lazy"></a>
              </c:when>
              <c:otherwise><span class="file-icon"><c:out value="${f.image ? 'IMG' : 'PDF'}"/></span></c:otherwise>
            </c:choose>
            <div class="meta">
              <strong><c:out value="${f.categoryLabel}"/></strong>: <c:out value="${f.originalName}"/>
              <small><c:out value="${f.mimeType}"/> &middot; <c:out value="${f.sizeLabel}"/> &middot; uploaded <c:out value="${f.uploadedAt}"/></small>
            </div>
            <c:choose>
              <c:when test="${f.available}">
                <a class="btn btn-ghost btn-sm" href="${ctx}/portal/file?id=${f.id}&amp;download=1">Download</a>
              </c:when>
              <c:otherwise><span class="badge badge-pending">File missing from storage</span></c:otherwise>
            </c:choose>
          </li>
        </c:forEach>
      </ul>
    </c:otherwise>
  </c:choose>
</section>

<%@ include file="/WEB-INF/views/partials/footer.jspf" %>
