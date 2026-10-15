package edu.kpriet.portal.model;

import java.util.Locale;

/** Metadata of one uploaded file (the bytes live in the shared upload folder). */
public class StudentFile {
    private int id;
    private int studentId;
    private String category;        // profile_image | id_proof | certificate | other
    private String originalName;
    private String storedName;
    private String relativePath;
    private String mimeType;
    private long fileSize;
    private String uploadedAt;      // already formatted for display
    private boolean available;      // false when the file is missing from disk

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }
    public int getStudentId() { return studentId; }
    public void setStudentId(int studentId) { this.studentId = studentId; }
    public String getCategory() { return category; }
    public void setCategory(String category) { this.category = category; }
    public String getOriginalName() { return originalName; }
    public void setOriginalName(String originalName) { this.originalName = originalName; }
    public String getStoredName() { return storedName; }
    public void setStoredName(String storedName) { this.storedName = storedName; }
    public String getRelativePath() { return relativePath; }
    public void setRelativePath(String relativePath) { this.relativePath = relativePath; }
    public String getMimeType() { return mimeType; }
    public void setMimeType(String mimeType) { this.mimeType = mimeType; }
    public long getFileSize() { return fileSize; }
    public void setFileSize(long fileSize) { this.fileSize = fileSize; }
    public String getUploadedAt() { return uploadedAt; }
    public void setUploadedAt(String uploadedAt) { this.uploadedAt = uploadedAt; }
    public boolean isAvailable() { return available; }
    public void setAvailable(boolean available) { this.available = available; }

    public boolean isImage() {
        return mimeType != null && mimeType.startsWith("image/");
    }

    public String getCategoryLabel() {
        switch (category == null ? "" : category) {
            case "profile_image": return "Profile image";
            case "id_proof": return "ID proof";
            case "certificate": return "Certificate";
            default: return "Other document";
        }
    }

    /** "6.9 KB" style text. */
    public String getSizeLabel() {
        if (fileSize < 1024) {
            return fileSize + " B";
        }
        if (fileSize < 1024 * 1024) {
            return String.format(Locale.ROOT, "%.1f KB", fileSize / 1024.0);
        }
        return String.format(Locale.ROOT, "%.1f MB", fileSize / (1024.0 * 1024.0));
    }
}
