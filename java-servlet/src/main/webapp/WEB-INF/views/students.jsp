<%@ page contentType="text/html;charset=UTF-8" pageEncoding="UTF-8" %>
<%@ taglib prefix="c" uri="jakarta.tags.core" %>
<c:set var="pageTitle" value="Students"/>
<%@ include file="/WEB-INF/views/partials/header.jspf" %>

<div class="page-head">
  <div>
    <h1>Student profiles</h1>
    <p><c:out value="${total}"/> student<c:if test="${total != 1}">s</c:if> found</p>
  </div>
</div>

<%-- Summary strip: values come from StudentDao.stats() --%>
<div class="stats">
  <div class="stat"><strong><c:out value="${stats.total}"/></strong><span>Students</span></div>
  <div class="stat"><strong><c:out value="${stats.active}"/></strong><span>Active</span></div>
  <div class="stat"><strong><c:out value="${stats.pending}"/></strong><span>Awaiting review</span></div>
  <div class="stat"><strong><c:out value="${stats.files}"/></strong><span>Files stored</span></div>
</div>

<%-- Filters use GET, so a filtered list can be bookmarked. --%>
<form class="toolbar" method="get" action="${ctx}/portal/students" role="search">
  <label class="sr-only" for="q">Search by name, roll number or email</label>
  <input type="search" id="q" name="q" placeholder="Search by name, roll number or email" value="<c:out value='${q}'/>">
  <label class="sr-only" for="course">Course</label>
  <select id="course" name="course">
    <option value="0">All courses</option>
    <c:forEach var="course" items="${courses}">
      <option value="${course.id}" ${course.id == courseId ? 'selected' : ''}><c:out value="${course.code}"/></option>
    </c:forEach>
  </select>
  <label class="sr-only" for="status">Status</label>
  <select id="status" name="status">
    <option value="">All statuses</option>
    <option value="active" ${status == 'active' ? 'selected' : ''}>Active</option>
    <option value="pending" ${status == 'pending' ? 'selected' : ''}>Pending</option>
    <option value="inactive" ${status == 'inactive' ? 'selected' : ''}>Inactive</option>
  </select>
  <button class="btn btn-ghost" type="submit">Filter</button>
</form>

<c:choose>
  <%-- Empty state --%>
  <c:when test="${empty students}">
    <div class="empty">
      <h3>No students match</h3>
      <p>Try a different search or clear the filters.</p>
      <a class="btn" href="${ctx}/portal/students">Clear filters</a>
    </div>
  </c:when>

  <c:otherwise>
    <div class="student-grid">
      <%-- Loop: one card per student --%>
      <c:forEach var="s" items="${students}">
        <article class="student-card">
          <c:choose>
            <%-- Conditional: real photo through the image-serving servlet, or an initials badge --%>
            <c:when test="${not empty s.profileFileId}">
              <img class="photo" src="${ctx}/portal/file?id=${s.profileFileId}" alt="Profile photo of <c:out value='${s.fullName}'/>" width="72" height="72" loading="lazy">
            </c:when>
            <c:otherwise>
              <div class="photo-fallback" aria-hidden="true"><c:out value="${s.initials}"/></div>
            </c:otherwise>
          </c:choose>
          <div>
            <h3><a href="${ctx}/portal/student?id=${s.id}"><c:out value="${s.fullName}"/></a></h3>
            <p><c:out value="${s.rollNumber}"/> &middot; <c:out value="${s.courseCode}"/> &middot; Year <c:out value="${s.yearOfStudy}"/></p>
            <p>
              <span class="badge badge-${s.status}"><c:out value="${s.status}"/></span>
              <c:if test="${s.fileCount > 0}">&nbsp;<c:out value="${s.fileCount}"/> file<c:if test="${s.fileCount != 1}">s</c:if></c:if>
            </p>
          </div>
        </article>
      </c:forEach>
    </div>

    <c:if test="${pages > 1}">
      <nav class="pagination" aria-label="Pagination">
        <c:if test="${page > 1}">
          <c:url var="prevUrl" value="/portal/students"><c:param name="q" value="${q}"/><c:param name="course" value="${courseId}"/><c:param name="status" value="${status}"/><c:param name="page" value="${page - 1}"/></c:url>
          <a href="<c:out value='${prevUrl}'/>">Previous</a>
        </c:if>
        <c:forEach var="n" begin="1" end="${pages}">
          <c:choose>
            <c:when test="${n == page}"><span class="current" aria-current="page"><c:out value="${n}"/></span></c:when>
            <c:otherwise>
              <c:url var="pageUrl" value="/portal/students"><c:param name="q" value="${q}"/><c:param name="course" value="${courseId}"/><c:param name="status" value="${status}"/><c:param name="page" value="${n}"/></c:url>
              <a href="<c:out value='${pageUrl}'/>"><c:out value="${n}"/></a>
            </c:otherwise>
          </c:choose>
        </c:forEach>
        <c:if test="${page < pages}">
          <c:url var="nextUrl" value="/portal/students"><c:param name="q" value="${q}"/><c:param name="course" value="${courseId}"/><c:param name="status" value="${status}"/><c:param name="page" value="${page + 1}"/></c:url>
          <a href="<c:out value='${nextUrl}'/>">Next</a>
        </c:if>
      </nav>
    </c:if>
  </c:otherwise>
</c:choose>

<%@ include file="/WEB-INF/views/partials/footer.jspf" %>
