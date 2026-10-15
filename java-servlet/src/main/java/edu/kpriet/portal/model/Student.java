package edu.kpriet.portal.model;

/** One student. Getters follow JavaBean naming so JSP expressions such as ${student.fullName} work. */
public class Student {
    private int id;
    private String rollNumber;
    private String fullName;
    private String email;
    private String phone;
    private String dateOfBirth;     // ISO text (yyyy-mm-dd) or null
    private String gender;
    private String courseCode;
    private String courseName;
    private int yearOfStudy;
    private String address;
    private String status;          // pending | active | inactive
    private String createdAt;       // already formatted for display
    private Integer profileFileId;  // id of the newest profile image, or null when there is none
    private int fileCount;

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }
    public String getRollNumber() { return rollNumber; }
    public void setRollNumber(String rollNumber) { this.rollNumber = rollNumber; }
    public String getFullName() { return fullName; }
    public void setFullName(String fullName) { this.fullName = fullName; }
    public String getEmail() { return email; }
    public void setEmail(String email) { this.email = email; }
    public String getPhone() { return phone; }
    public void setPhone(String phone) { this.phone = phone; }
    public String getDateOfBirth() { return dateOfBirth; }
    public void setDateOfBirth(String dateOfBirth) { this.dateOfBirth = dateOfBirth; }
    public String getGender() { return gender; }
    public void setGender(String gender) { this.gender = gender; }
    public String getCourseCode() { return courseCode; }
    public void setCourseCode(String courseCode) { this.courseCode = courseCode; }
    public String getCourseName() { return courseName; }
    public void setCourseName(String courseName) { this.courseName = courseName; }
    public int getYearOfStudy() { return yearOfStudy; }
    public void setYearOfStudy(int yearOfStudy) { this.yearOfStudy = yearOfStudy; }
    public String getAddress() { return address; }
    public void setAddress(String address) { this.address = address; }
    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }
    public String getCreatedAt() { return createdAt; }
    public void setCreatedAt(String createdAt) { this.createdAt = createdAt; }
    public Integer getProfileFileId() { return profileFileId; }
    public void setProfileFileId(Integer profileFileId) { this.profileFileId = profileFileId; }
    public int getFileCount() { return fileCount; }
    public void setFileCount(int fileCount) { this.fileCount = fileCount; }

    /** Up to two capital letters, shown when the student has no profile image. */
    public String getInitials() {
        StringBuilder sb = new StringBuilder();
        if (fullName != null) {
            for (String part : fullName.trim().split("\\s+")) {
                if (!part.isEmpty() && sb.length() < 2) {
                    sb.appendCodePoint(Character.toUpperCase(part.codePointAt(0)));
                }
            }
        }
        return sb.toString();
    }
}
