import base64
import logging

import werkzeug

import odoo.http as http
from odoo.http import request
from odoo.tools import plaintext2html

_logger = logging.getLogger(__name__)


class HelpdeskTicketController(http.Controller):
    @http.route("/ticket/close", type="http", auth="user")
    def support_ticket_close(self, **kw):
        """Close the support ticket"""
        values = {}
        for field_name, field_value in kw.items():
            if field_name.endswith("_id"):
                values[field_name] = int(field_value)
            else:
                values[field_name] = field_value
        ticket = (
            http.request.env["helpdesk.ticket"]
            .sudo()
            .search([("id", "=", values["ticket_id"])])
        )
        stage = http.request.env["helpdesk.ticket.stage"].browse(values.get("stage_id"))
        if stage.close_from_portal:  # protect against invalid target stage request
            ticket.stage_id = values.get("stage_id")

        return werkzeug.utils.redirect("/my/ticket/" + str(ticket.id))

    def _get_teams(self):
        return (
            http.request.env["helpdesk.ticket.team"]
            .with_company(request.env.company.id)
            .search([("active", "=", True), ("show_in_portal", "=", True)])
            if http.request.env.user.company_id.helpdesk_mgmt_portal_select_team
            else False
        )

    @http.route("/new/ticket", type="http", auth="user", website=True)
    def create_new_ticket(self, **kw):
        session_info = http.request.env["ir.http"].session_info()
        company = request.env.company
        category_model = http.request.env["helpdesk.ticket.category"]
        categories = category_model.with_company(company.id).search(
            [("active", "=", True), ("show_in_portal", "=", True)]
        )
        email = http.request.env.user.email
        name = http.request.env.user.name
        company = request.env.company
        return http.request.render(
            "helpdesk_mgmt.portal_create_ticket",
            {
                "categories": categories,
                "teams": self._get_teams(),
                "email": email,
                "name": name,
                "ticket_team_id_required": (
                    company.helpdesk_mgmt_portal_team_id_required
                ),
                "ticket_category_id_required": (
                    company.helpdesk_mgmt_portal_category_id_required
                ),
                "max_upload_size": session_info["max_file_upload_size"],
            },
        )

    def _prepare_submit_ticket_vals(self, **kw):
        category = http.request.env["helpdesk.ticket.category"].browse(
            int(kw.get("category") or 0)
        )
        company = category.company_id or http.request.env.company
        vals = {
            "company_id": company.id,
            "category_id": category.id,
            "description": plaintext2html(kw.get("description")),
            "name": kw.get("subject"),
            "attachment_ids": False,
            "channel_id": request.env.ref(
                "helpdesk_mgmt.helpdesk_ticket_channel_web", False
            ).id,
            "partner_id": request.env.user.partner_id.id,
            "partner_name": request.env.user.partner_id.name,
            "partner_email": request.env.user.partner_id.email,
            "user_id": False,
        }
        team = http.request.env["helpdesk.ticket.team"]
        if company.helpdesk_mgmt_portal_select_team and kw.get("team"):
            team = (
                http.request.env["helpdesk.ticket.team"]
                .sudo()
                .search(
                    [("id", "=", int(kw.get("team"))), ("show_in_portal", "=", True)]
                )
            )
            vals["team_id"] = team.id
        # Need to set stage_id so that the _track_template() method is called
        # and the mail is sent automatically if applicable
        vals["stage_id"] = team._get_applicable_stages()[:1].id
        return vals

    @http.route("/submitted/ticket", type="http", auth="user", website=True, csrf=True)
    def submit_ticket(self, **kw):
        vals = self._prepare_submit_ticket_vals(**kw)
        new_ticket = request.env["helpdesk.ticket"].sudo().create(vals)
        new_ticket.message_subscribe(partner_ids=request.env.user.partner_id.ids)
        if kw.get("attachment"):
            IrAttachment = request.env["ir.attachment"]
            attachment_ids = IrAttachment
            for c_file in request.httprequest.files.getlist("attachment"):
                data = c_file.read()
                if c_file.filename:
                    attachment_ids += IrAttachment.sudo().create(
                        {
                            "name": c_file.filename,
                            "datas": base64.b64encode(data),
                            "res_model": "helpdesk.ticket",
                            "res_id": new_ticket.id,
                        }
                    )
            attachment_ids.sudo().generate_access_token()
        return werkzeug.utils.redirect("/my/ticket/%s" % new_ticket.id)

    # ------------------------------------------------------------------
    # Public healthcare worker account request form (no login required)
    # ------------------------------------------------------------------

    def _healthcare_page(self, body_html, title="ESMOS Healthcare Portal"):
        """Wrap body HTML in a minimal standalone Bootstrap page."""
        html = """<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>{title}</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"/>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary mb-4">
        <div class="container">
            <span class="navbar-brand fw-bold">ESMOS Staff Portal</span>
        </div>
    </nav>
    <div class="container">
        {body}
    </div>
</body>
</html>""".format(
            title=title, body=body_html
        )
        return request.make_response(html, headers=[("Content-Type", "text/html")])

    @http.route("/healthcare/request-access", type="http", auth="public", website=False)
    def healthcare_request_form(self, error="", **kw):
        """Render the public form for healthcare workers to request an Odoo account."""
        csrf_token = request.csrf_token()
        error_html = (
            '<div class="alert alert-danger">{}</div>'.format(error) if error else ""
        )
        body = """
<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-primary text-white">
        <h4 class="mb-0">Request Odoo Account Access</h4>
        <small>For ESMOS healthcare workers who have completed staff training</small>
      </div>
      <div class="card-body">
        {error_html}
        <p class="text-muted mb-4">
          Once you have completed the <strong>ESMOS Staff Training - Using Odoo</strong>
          course on Moodle, submit this form. The helpdesk team will create your
          Odoo account within one business day.
        </p>
        <form action="/healthcare/submit-request" method="POST">
          <input type="hidden" name="csrf_token" value="{csrf}"/>
          <div class="mb-3">
            <label class="form-label fw-bold" for="full_name">
              Full Name <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" id="full_name" name="full_name"
                   placeholder="e.g. Alice Tan" required/>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold" for="email">
              Work Email <span class="text-danger">*</span>
            </label>
            <input type="email" class="form-control" id="email" name="email"
                   placeholder="e.g. alice@esmos.internal" required/>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold" for="moodle_username">
              Moodle Username <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" id="moodle_username"
                   name="moodle_username" placeholder="e.g. staff1" required/>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold" for="course_name">Course Completed</label>
            <input type="text" class="form-control bg-light" id="course_name"
                   name="course_name" value="ESMOS Staff Training - Using Odoo" readonly/>
          </div>
          <div class="mb-4 form-check">
            <input class="form-check-input" type="checkbox"
                   id="confirm_completion" required/>
            <label class="form-check-label" for="confirm_completion">
              I confirm that I have completed the training course on Moodle.
            </label>
          </div>
          <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">Submit Request</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>""".format(
            error_html=error_html, csrf=csrf_token
        )
        return self._healthcare_page(body, title="Request Odoo Access")

    @http.route(
        "/healthcare/submit-request",
        type="http",
        auth="public",
        website=False,
        csrf=True,
        methods=["POST"],
    )
    def healthcare_submit_request(self, **kw):
        """Create a helpdesk ticket from the public healthcare worker request form."""
        full_name = kw.get("full_name", "").strip()
        email = kw.get("email", "").strip()
        moodle_username = kw.get("moodle_username", "").strip()
        course_name = kw.get("course_name", "ESMOS Staff Training - Using Odoo").strip()

        if not full_name or not email or not moodle_username:
            return self.healthcare_request_form(
                error="Please fill in all required fields."
            )

        description_html = (
            "<p><strong>New Odoo Account Request from Healthcare Worker</strong></p>"
            "<ul>"
            "<li><strong>Full Name:</strong> {name}</li>"
            "<li><strong>Email:</strong> {email}</li>"
            "<li><strong>Moodle Username:</strong> {username}</li>"
            "<li><strong>Course Completed:</strong> {course}</li>"
            "</ul>"
            "<p>Please create an Odoo account for this user.</p>"
        ).format(
            name=full_name,
            email=email,
            username=moodle_username,
            course=course_name,
        )

        team = request.env["helpdesk.ticket.team"].sudo()
        web_channel = request.env.ref(
            "helpdesk_mgmt.helpdesk_ticket_channel_web", False
        )
        vals = {
            "name": "Odoo Account Request - %s" % full_name,
            "description": description_html,
            "partner_name": full_name,
            "partner_email": email,
            "channel_id": web_channel.id if web_channel else False,
            "stage_id": team._get_applicable_stages()[:1].id,
        }
        request.env["helpdesk.ticket"].sudo().create(vals)

        body = """
<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6 text-center">
    <div class="card shadow-sm">
      <div class="card-body p-5">
        <div class="mb-3 text-success" style="font-size:4rem;">&#10003;</div>
        <h2 class="text-success mb-3">Request Submitted!</h2>
        <p class="lead">Thank you, <strong>{name}</strong>.</p>
        <p class="text-muted">
          Your request has been received by the helpdesk team.
          They will review your training completion and create your Odoo account
          within one business day.
        </p>
        <p class="text-muted">
          You will receive your login credentials at the email address you provided.
        </p>
        <hr/>
        <a href="/healthcare/request-access" class="btn btn-outline-primary mt-2">
          Submit Another Request
        </a>
      </div>
    </div>
  </div>
</div>""".format(
            name=full_name
        )
        return self._healthcare_page(body, title="Request Submitted")
