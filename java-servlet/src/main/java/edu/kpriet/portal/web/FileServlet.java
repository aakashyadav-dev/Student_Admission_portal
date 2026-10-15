package edu.kpriet.portal.web;

import edu.kpriet.portal.config.AppConfig;
import edu.kpriet.portal.dao.StudentFileDao;
import edu.kpriet.portal.model.StudentFile;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServlet;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;

import java.io.IOException;
import java.io.OutputStream;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.sql.SQLException;
import java.util.Set;
import java.util.logging.Level;
import java.util.logging.Logger;

/**
 * Streams an uploaded image or document to a signed-in user (the AuthFilter already checked the session).
 *   <img src=".../portal/file?id=7">                 shows an image inline
 *   <a href=".../portal/file?id=7&download=1">       forces a download
 * The URL carries only a database id. The path comes from the database row and is re-validated by
 * UploadStorage, so a visitor can never ask for an arbitrary file on the server.
 */
public class FileServlet extends HttpServlet {

    private static final Logger LOG = Logger.getLogger(FileServlet.class.getName());
    private static final Set<String> SERVABLE = Set.of("image/jpeg", "image/png", "image/webp", "application/pdf");

    private final StudentFileDao files = new StudentFileDao();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws ServletException, IOException {
        Integer id = WebUtil.parsePositiveInt(req.getParameter("id"));
        if (id == null) {
            resp.sendError(HttpServletResponse.SC_BAD_REQUEST);                     // invalid request
            return;
        }

        StudentFile meta;
        try {
            meta = files.findById(id);
        } catch (SQLException e) {
            LOG.log(Level.SEVERE, "Could not look up file " + id, e);
            resp.sendError(HttpServletResponse.SC_INTERNAL_SERVER_ERROR);           // database error
            return;
        }
        if (meta == null) {
            resp.sendError(HttpServletResponse.SC_NOT_FOUND);
            return;
        }

        Path path = UploadStorage.resolve(AppConfig.get().uploadRoot(), meta.getRelativePath());
        if (path == null) {
            LOG.warning("File " + id + " is recorded in the database but missing or invalid on disk.");
            resp.sendError(HttpServletResponse.SC_NOT_FOUND);                       // missing file
            return;
        }

        String mime = SERVABLE.contains(meta.getMimeType()) ? meta.getMimeType() : "application/octet-stream";
        boolean image = mime.startsWith("image/");
        boolean asDownload = req.getParameter("download") != null || !image;
        String asciiName = meta.getOriginalName().replaceAll("[^A-Za-z0-9._-]", "_");
        String utf8Name = URLEncoder.encode(meta.getOriginalName(), StandardCharsets.UTF_8).replace("+", "%20");

        resp.setContentType(mime);
        resp.setContentLengthLong(Files.size(path));
        resp.setHeader("Content-Disposition", (asDownload ? "attachment" : "inline")
            + "; filename=\"" + asciiName + "\"; filename*=UTF-8''" + utf8Name);
        resp.setHeader("X-Content-Type-Options", "nosniff");
        resp.setHeader("Content-Security-Policy", "default-src 'none'; sandbox");   // even if sniffed as HTML, nothing can run
        resp.setHeader("Cache-Control", "private, max-age=300");

        try (OutputStream out = resp.getOutputStream()) {
            Files.copy(path, out);                                                  // streams the bytes in chunks
        }
    }
}
