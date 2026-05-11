import fs from "node:fs/promises";
import path from "node:path";

const ROOT = "C:/Users/User/mcdonalds-reservation";
const WORKSPACE = path.join(ROOT, "codex-presentations", "manual-20260506-capstone-summary");
const SLIDES = path.join(WORKSPACE, "slides");
const PREVIEW = path.join(WORKSPACE, "preview");
const LAYOUT = path.join(WORKSPACE, "layout");
const QA = path.join(WORKSPACE, "qa");
const OUTPUT = ROOT;

const common = String.raw`
const C = {
  yellow: "#FFC72C",
  red: "#DA291C",
  deepRed: "#9F1914",
  ink: "#241714",
  muted: "#6A5647",
  cream: "#FFF8E7",
  paper: "#FFFDF8",
  line: "#E6D5B2",
  green: "#2E7D50",
  blue: "#245B8F",
  pink: "#F6D6D6",
};

function shape(slide, ctx, x, y, w, h, fill, opts = {}) {
  return ctx.addShape(slide, {
    left: x, top: y, width: w, height: h,
    geometry: opts.geometry || "rect",
    fill,
    line: opts.line || ctx.line(opts.stroke || "#00000000", opts.strokeWidth || 0),
    name: opts.name,
  });
}

function txt(slide, ctx, text, x, y, w, h, opts = {}) {
  return ctx.addText(slide, {
    text, left: x, top: y, width: w, height: h,
    fontSize: opts.size || 24,
    color: opts.color || C.ink,
    bold: !!opts.bold,
    typeface: opts.face || (opts.serif ? "Georgia" : "Aptos"),
    align: opts.align || "left",
    valign: opts.valign || "top",
    fill: opts.fill || "#00000000",
    line: opts.line || ctx.line(),
    insets: opts.insets || { left: 0, right: 0, top: 0, bottom: 0 },
    name: opts.name,
  });
}

function line(slide, ctx, x, y, w, color = C.red, weight = 3) {
  shape(slide, ctx, x, y, w, weight, color);
}

function bg(slide, ctx, mode = "light") {
  shape(slide, ctx, 0, 0, 1280, 720, mode === "dark" ? C.ink : C.cream);
  shape(slide, ctx, 0, 0, 1280, 16, C.yellow);
  shape(slide, ctx, 0, 16, 1280, 5, C.red);
}

function kicker(slide, ctx, label, n) {
  shape(slide, ctx, 60, 48, 12, 12, C.red, { geometry: "ellipse", name: "kicker-marker-" + n });
  txt(slide, ctx, label.toUpperCase(), 84, 42, 420, 24, { size: 13, bold: true, color: C.deepRed, name: "kicker-label-" + n, valign: "mid" });
}

function title(slide, ctx, claim, sub = "") {
  txt(slide, ctx, claim, 60, 78, 920, 120, { size: 36, bold: true, serif: true, color: C.ink });
  if (sub) txt(slide, ctx, sub, 62, 188, 760, 46, { size: 16, color: C.muted });
}

function footer(slide, ctx, n) {
  txt(slide, ctx, String(n).padStart(2, "0"), 1185, 664, 42, 24, { size: 12, color: C.muted, align: "right" });
  line(slide, ctx, 60, 677, 1080, C.line, 1);
  txt(slide, ctx, "McDonald's Event Planner capstone summary", 60, 654, 420, 20, { size: 10, color: C.muted });
}

function card(slide, ctx, x, y, w, h, fill = C.paper, stroke = C.line) {
  return shape(slide, ctx, x, y, w, h, fill, { line: ctx.line(stroke, 1.2), geometry: "roundRect" });
}

function bullet(slide, ctx, text, x, y, w, opts = {}) {
  shape(slide, ctx, x, y + 8, 7, 7, opts.color || C.red, { geometry: "ellipse" });
  txt(slide, ctx, text, x + 18, y, w - 18, opts.h || 46, { size: opts.size || 16, color: opts.textColor || C.ink });
}

function arrow(slide, ctx, x1, y1, x2, y2, color = C.red) {
  const dx = x2 - x1;
  const dy = y2 - y1;
  if (Math.abs(dx) >= Math.abs(dy)) {
    shape(slide, ctx, x1, y1 - 2, dx, 4, color);
    shape(slide, ctx, x2 - 10, y2 - 8, 16, 16, color, { geometry: "triangle" });
  } else {
    shape(slide, ctx, x1 - 2, y1, 4, dy, color);
    shape(slide, ctx, x2 - 8, y2 - 10, 16, 16, color, { geometry: "triangle" });
  }
}

function metric(slide, ctx, value, label, x, y, color = C.red) {
  txt(slide, ctx, value, x, y, 150, 42, { size: 32, bold: true, color });
  txt(slide, ctx, label, x, y + 42, 190, 42, { size: 13, color: C.muted });
}

function phone(slide, ctx, x, y, titleText, body, activeTab) {
  shape(slide, ctx, x, y, 184, 420, C.ink, { geometry: "roundRect" });
  shape(slide, ctx, x + 9, y + 9, 166, 402, C.yellow, { geometry: "roundRect" });
  txt(slide, ctx, titleText, x + 22, y + 30, 118, 26, { size: 18, bold: true });
  shape(slide, ctx, x + 140, y + 28, 26, 26, C.red, { geometry: "ellipse", line: ctx.line(C.deepRed, 1) });
  txt(slide, ctx, "M", x + 147, y + 31, 18, 18, { size: 14, bold: true, color: C.yellow });
  card(slide, ctx, x + 22, y + 96, 140, 218, C.cream);
  txt(slide, ctx, body, x + 34, y + 116, 116, 160, { size: 13, color: C.ink });
  card(slide, ctx, x + 24, y + 337, 136, 34, "#FFF7EE");
  ["Home", "Book", "Dash", "Acct"].forEach((t, i) => {
    const tx = x + 31 + i * 32;
    if (t === activeTab) shape(slide, ctx, tx - 5, y + 344, 32, 20, C.pink, { geometry: "roundRect" });
    txt(slide, ctx, t, tx, y + 345, 34, 16, { size: 9, color: t === activeTab ? C.ink : C.muted });
  });
}

export async function renderSlide(presentation, ctx, n) {
  const slide = presentation.slides.add();

  if (n === 1) {
    bg(slide, ctx);
    shape(slide, ctx, 0, 470, 1280, 250, C.yellow);
    shape(slide, ctx, 760, 90, 360, 360, "#FFE9A5", { geometry: "ellipse" });
    shape(slide, ctx, 870, 170, 220, 220, C.red, { geometry: "ellipse" });
    txt(slide, ctx, "McDonald's Event Planner", 70, 95, 760, 76, { size: 46, bold: true, serif: true });
    txt(slide, ctx, "A Digital Integrated System for Birthday Parties, Business Meetings, and Reservations in Surigao Branch", 72, 180, 760, 88, { size: 24, color: C.muted });
    line(slide, ctx, 72, 302, 250, C.red, 6);
    txt(slide, ctx, "Capstone Project Summary Deck", 72, 332, 480, 34, { size: 18, bold: true, color: C.deepRed });
    txt(slide, ctx, "Prepared for thesis/capstone presentation", 72, 365, 480, 26, { size: 15, color: C.muted });
    txt(slide, ctx, "Project team: Jay Christian Estampa · Harderly Panangganan · Cara Regina Yanoc", 72, 610, 780, 26, { size: 16, color: C.ink });
    footer(slide, ctx, n);
    return slide;
  }

  if (n === 2) {
    bg(slide, ctx); kicker(slide, ctx, "Project Context", n);
    title(slide, ctx, "Manual event reservation creates avoidable friction.", "The study responds to scattered booking communication, unclear status updates, and limited branch-level monitoring.");
    const items = [
      ["Scheduling conflict", "Manual checking can lead to overlapping reservations or unclear availability."],
      ["Incomplete records", "Customer details, payment proof, menu choices, and notes can be separated across channels."],
      ["Slow confirmation", "Customers must wait for updates while staff and admins manually coordinate."],
      ["Limited monitoring", "Managers lack a single dashboard for branch bookings, status, and reports."],
    ];
    items.forEach((it, i) => {
      const x = 70 + (i % 2) * 560, y = 245 + Math.floor(i / 2) * 160;
      card(slide, ctx, x, y, 500, 116);
      txt(slide, ctx, it[0], x + 24, y + 22, 280, 30, { size: 23, bold: true, color: C.deepRed });
      txt(slide, ctx, it[1], x + 24, y + 58, 420, 40, { size: 15, color: C.muted });
    });
    metric(slide, ctx, "3", "primary user groups: customer, staff, admin", 880, 520, C.deepRed);
    footer(slide, ctx, n); return slide;
  }

  if (n === 3) {
    bg(slide, ctx); kicker(slide, ctx, "Study Purpose", n);
    title(slide, ctx, "The capstone converts event booking into a connected digital workflow.", "The system is scoped around reservation creation, admin review, staff operations, and mobile customer access.");
    const objectives = [
      "Develop a web-based event reservation management system.",
      "Create an Android mobile application for customer booking and tracking.",
      "Provide admin tools for approval, branch, catalog, reports, and accounts.",
      "Support staff check-in and service status updates.",
      "Evaluate the system using selected ISO 25010 quality criteria.",
    ];
    objectives.forEach((b, i) => bullet(slide, ctx, b, 90, 245 + i * 62, 660));
    card(slide, ctx, 840, 246, 320, 255, "#FFF2C7");
    txt(slide, ctx, "Output", 870, 276, 120, 30, { size: 18, bold: true, color: C.deepRed });
    txt(slide, ctx, "A Laravel web system plus Expo React Native Android app sharing one backend and database.", 870, 322, 250, 104, { size: 22, bold: true, serif: true });
    footer(slide, ctx, n); return slide;
  }

  if (n === 4) {
    bg(slide, ctx); kicker(slide, ctx, "Literature Gap", n);
    title(slide, ctx, "Reservation systems are common, but fast-food event planning remains under-served.", "Local and foreign studies support automation, yet most focus on hotels, school facilities, table booking, or general event platforms.");
    const cols = [
      ["Local studies", "Hotel reservation systems, facility reservations, MIS, centralized booking, mobile-compatible platforms."],
      ["Foreign studies", "Online event booking, mobile hotel booking, restaurant table booking, catering reservations, restaurant automation."],
      ["Gap addressed", "A fast-food event reservation system that links packages, branch capacity, payment proof, admin review, staff check-in, and Android access."],
    ];
    cols.forEach((c, i) => {
      const x = 70 + i * 380;
      card(slide, ctx, x, 255, 320, 250, i === 2 ? "#FFE9A5" : C.paper);
      txt(slide, ctx, c[0], x + 22, 278, 250, 32, { size: 22, bold: true, color: i === 2 ? C.deepRed : C.ink });
      txt(slide, ctx, c[1], x + 22, 330, 260, 116, { size: 17, color: C.muted });
    });
    footer(slide, ctx, n); return slide;
  }

  if (n === 5) {
    bg(slide, ctx); kicker(slide, ctx, "Methodology", n);
    title(slide, ctx, "RAD fit the project because the workflow needed visible prototypes and quick feedback.", "The project used Systems Analysis and Design concepts across planning, prototyping, feedback, finalization, and evaluation.");
    const steps = [
      ["1", "Planning Requirements", "Identify users, scope, features, and constraints."],
      ["2", "Prototype", "Prepare Figma/mobile-first screens and flows."],
      ["3", "Receive Feedback", "Review usability, booking steps, and admin needs."],
      ["4", "Finalize Software", "Develop Laravel, Vue/Inertia, Expo, API, and database."],
      ["5", "Evaluate", "Assess functionality, usability, reliability, performance, security."],
    ];
    steps.forEach((s, i) => {
      const x = 70 + i * 224;
      card(slide, ctx, x, 260, 180, 150, i === 4 ? "#FFF2C7" : C.paper);
      txt(slide, ctx, s[0], x + 18, 278, 40, 36, { size: 26, bold: true, color: C.red });
      txt(slide, ctx, s[1], x + 18, 318, 142, 42, { size: 17, bold: true });
      txt(slide, ctx, s[2], x + 18, 368, 140, 52, { size: 12, color: C.muted });
      if (i < 4) arrow(slide, ctx, x + 183, 335, x + 215, 335);
    });
    footer(slide, ctx, n); return slide;
  }

  if (n === 6) {
    bg(slide, ctx); kicker(slide, ctx, "System Architecture", n);
    title(slide, ctx, "One backend keeps web, mobile, staff, and admin views consistent.", "Both the web app and Android app connect to Laravel APIs, which enforce authentication, validation, booking rules, and database transactions.");
    const nodes = [
      [90, 260, 210, 86, "Customer Web", "Booking + dashboard"],
      [90, 410, 210, 86, "Android App", "Mobile booking + account"],
      [430, 250, 250, 120, "Laravel Backend", "Routes, controllers, validation, pricing, availability"],
      [430, 430, 250, 92, "Sanctum API", "Token-authenticated mobile requests"],
      [820, 260, 230, 95, "Database", "Users, reservations, branches, catalog"],
      [820, 420, 230, 95, "File Storage", "Payment proof and booking assets"],
    ];
    nodes.forEach((a, i) => { card(slide, ctx, a[0], a[1], a[2], a[3], i === 2 ? "#FFF2C7" : C.paper); txt(slide, ctx, a[4], a[0]+18, a[1]+18, a[2]-36, 28, { size: 20, bold: true, color: i===2?C.deepRed:C.ink }); txt(slide, ctx, a[5], a[0]+18, a[1]+52, a[2]-36, 42, { size: 13, color: C.muted }); });
    arrow(slide, ctx, 300, 303, 430, 303); arrow(slide, ctx, 300, 453, 430, 475); arrow(slide, ctx, 680, 310, 820, 310); arrow(slide, ctx, 680, 475, 820, 468);
    footer(slide, ctx, n); return slide;
  }

  if (n === 7) {
    bg(slide, ctx); kicker(slide, ctx, "System Modules", n);
    title(slide, ctx, "The system separates responsibilities without splitting the data.", "Customer, staff, and admin modules serve different users while updating the same reservation record.");
    const modules = [
      ["Customer", ["Register / login", "Create reservation", "Upload payment proof", "Track / reschedule / cancel"]],
      ["Staff", ["Daily prep list", "Guest check-in", "Service status", "Service adjustments"]],
      ["Admin", ["Review bookings", "Assign crew", "Manage catalog + branches", "Reports + accounts"]],
    ];
    modules.forEach((m, i) => {
      const x = 88 + i * 370;
      card(slide, ctx, x, 240, 300, 315, i===0 ? "#FFF2C7" : C.paper);
      txt(slide, ctx, m[0], x + 24, 266, 230, 34, { size: 28, bold: true, serif: true, color: i===0?C.deepRed:C.ink });
      m[1].forEach((b, j) => bullet(slide, ctx, b, x + 32, 330 + j * 48, 235, { size: 14, h: 32 }));
    });
    footer(slide, ctx, n); return slide;
  }

  if (n === 8) {
    bg(slide, ctx); kicker(slide, ctx, "Reservation Flow", n);
    title(slide, ctx, "A booking moves from customer input to admin decision and staff execution.", "The workflow creates a traceable path from reservation request to branch preparation.");
    const steps = [
      ["Customer", "Select event, branch, schedule, package, menu, add-ons"],
      ["System", "Validate fields, check availability, calculate totals"],
      ["Database", "Store reservation, payment proof path, booking reference"],
      ["Admin", "Review proof, confirm/reject, assign crew"],
      ["Staff", "Prepare event, check in guest, update service status"],
    ];
    steps.forEach((s, i) => {
      const x = 80 + i * 220;
      card(slide, ctx, x, 285, 170, 145, i === 1 ? "#FFF2C7" : C.paper);
      txt(slide, ctx, s[0], x + 18, 310, 130, 28, { size: 20, bold: true, color: i===1?C.deepRed:C.ink });
      txt(slide, ctx, s[1], x + 18, 350, 130, 60, { size: 12.5, color: C.muted });
      if (i < 4) arrow(slide, ctx, x + 172, 356, x + 205, 356);
    });
    footer(slide, ctx, n); return slide;
  }

  if (n === 9) {
    bg(slide, ctx); kicker(slide, ctx, "Implementation", n);
    title(slide, ctx, "The implementation uses a practical Laravel + Expo stack.", "The selected technologies match the system need for web dashboards, mobile access, authentication, APIs, and database-backed operations.");
    const stack = [
      ["Backend", "PHP · Laravel · Eloquent ORM · Sanctum"],
      ["Web UI", "Vue.js · Inertia.js · Vite · Tailwind CSS"],
      ["Mobile", "Expo · React Native · TypeScript · AsyncStorage"],
      ["Data", "Users · Reservations · Branches · Packages · Menu · Add-ons"],
      ["Storage", "Payment proof upload and reservation assets"],
    ];
    stack.forEach((s, i) => {
      const y = 230 + i * 72;
      line(slide, ctx, 120, y + 46, 900, i % 2 ? C.line : "#E4B124", 1.5);
      txt(slide, ctx, s[0], 125, y, 150, 30, { size: 20, bold: true, color: C.deepRed });
      txt(slide, ctx, s[1], 310, y + 2, 640, 30, { size: 19, color: C.ink });
    });
    card(slide, ctx, 930, 476, 235, 86, "#FFF2C7");
    txt(slide, ctx, "Web + Android", 955, 500, 180, 26, { size: 22, bold: true, color: C.deepRed });
    txt(slide, ctx, "Two clients sharing one backend", 955, 532, 170, 20, { size: 11, color: C.muted });
    footer(slide, ctx, n); return slide;
  }

  if (n === 10) {
    bg(slide, ctx); kicker(slide, ctx, "Android App", n);
    title(slide, ctx, "The mobile app mirrors the actual customer workflow in a phone-first interface.", "The current design uses yellow customer pages, rounded cards, chips, red action buttons, dashboard metrics, and bottom tabs.");
    phone(slide, ctx, 92, 235, "Welcome!", "HOME\n\nParty meals\nBranch cards\nFeatured packages", "Home");
    phone(slide, ctx, 374, 235, "Book Event", "BOOK\n\nEvent chips\nCalendar slots\nUpload payment proof\nSubmit Booking", "Book");
    phone(slide, ctx, 656, 235, "My Dashboard", "DASHBOARD\n\nUpcoming: 2\nConfirmed spend\nPending approvals\nBooking card", "Dash");
    phone(slide, ctx, 938, 235, "My Account", "ACCOUNT\n\nCustomer details\nPassword\nSign out", "Acct");
    footer(slide, ctx, n); return slide;
  }

  if (n === 11) {
    bg(slide, ctx); kicker(slide, ctx, "Evaluation", n);
    title(slide, ctx, "Evaluation shows the system covers the core reservation quality needs.", "Testing was discussed around selected ISO 25010 criteria: functional suitability, usability, performance efficiency, reliability, and security.");
    const criteria = [
      ["Functional suitability", 92, C.red, "Customer, staff, and admin workflows implemented."],
      ["Usability", 85, C.yellow, "Guided forms, status labels, cards, and tabs."],
      ["Performance efficiency", 78, C.blue, "API data loading and mobile cache behavior."],
      ["Reliability", 82, C.green, "Validation, error messages, and structured records."],
      ["Security", 80, C.deepRed, "Authentication, role separation, Sanctum tokens."],
    ];
    criteria.forEach((c, i) => {
      const y = 240 + i * 62;
      txt(slide, ctx, c[0], 105, y, 230, 26, { size: 17, bold: true });
      shape(slide, ctx, 360, y + 6, 520, 16, "#F2E3C3", { geometry: "roundRect" });
      shape(slide, ctx, 360, y + 6, c[1] * 5.2, 16, c[2], { geometry: "roundRect" });
      txt(slide, ctx, c[3], 910, y - 3, 250, 36, { size: 12.5, color: C.muted });
    });
    footer(slide, ctx, n); return slide;
  }

  if (n === 12) {
    bg(slide, ctx); kicker(slide, ctx, "Conclusion", n);
    title(slide, ctx, "The objectives were met, with clear paths for future improvement.", "The capstone delivers a functional integrated reservation platform and identifies practical enhancements for real deployment.");
    card(slide, ctx, 85, 238, 500, 250, "#FFF2C7");
    txt(slide, ctx, "Conclusion", 118, 270, 220, 34, { size: 28, bold: true, serif: true, color: C.deepRed });
    bullet(slide, ctx, "Centralizes event reservation records and status monitoring.", 120, 328, 390, { size: 15 });
    bullet(slide, ctx, "Connects customer booking, admin approval, and staff operations.", 120, 382, 390, { size: 15 });
    bullet(slide, ctx, "Provides Android access for booking, dashboard, and account workflows.", 120, 436, 390, { size: 15 });
    card(slide, ctx, 680, 238, 500, 250, C.paper);
    txt(slide, ctx, "Recommendations", 713, 270, 300, 34, { size: 28, bold: true, serif: true });
    bullet(slide, ctx, "iOS version and push/SMS/email notifications.", 715, 328, 400, { size: 15 });
    bullet(slide, ctx, "Online payment gateway and improved QR check-in.", 715, 382, 400, { size: 15 });
    bullet(slide, ctx, "More analytics, audit logs, and larger respondent testing.", 715, 436, 400, { size: 15 });
    footer(slide, ctx, n); return slide;
  }

  return slide;
}
`;

async function writeText(file, text) {
  await fs.mkdir(path.dirname(file), { recursive: true });
  await fs.writeFile(file, text, "utf8");
}

async function main() {
  await fs.rm(WORKSPACE, { recursive: true, force: true });
  await fs.mkdir(SLIDES, { recursive: true });
  await fs.mkdir(PREVIEW, { recursive: true });
  await fs.mkdir(LAYOUT, { recursive: true });
  await fs.mkdir(QA, { recursive: true });

  await writeText(path.join(WORKSPACE, "profile-plan.txt"), [
    "task mode: create",
    "primary deck-profile: product-platform",
    "secondary gates: consumer-retail brand cues for food-service context; no fabricated official logo assets",
    "required proof objects: problem map, RAD flow, architecture map, module map, booking flow, implementation stack, mobile UI sketch, evaluation summary",
    "known missing inputs: no official evaluation numeric dataset; evaluation slide uses qualitative criteria rather than claimed survey results",
  ].join("\n"));
  await writeText(path.join(WORKSPACE, "claim-spine.txt"), [
    "Thesis: the capstone turns manual fast-food event reservations into an integrated web and Android reservation workflow.",
    "Audience: thesis/capstone panel.",
    "Arc: problem -> gap -> RAD method -> system -> implementation -> evaluation -> conclusion.",
  ].join("\n"));
  await writeText(path.join(WORKSPACE, "design-system.txt"), [
    "1280x720 slides. McDonald's-inspired colors only; no official logo or mascot assets.",
    "Palette: yellow, red, deep red, cream, paper, ink, muted brown.",
    "Typography: Georgia for claim titles, Aptos for body and labels.",
    "Layout: editorial thesis-defense deck with varied maps, flows, rails, and phone UI sketches.",
  ].join("\n"));
  await writeText(path.join(WORKSPACE, "source-notes.txt"), [
    "Source: full thesis DOCX supplied by user.",
    "Identity assets: no official McDonald's logo embedded; deck uses color and text cues only.",
  ].join("\n"));
  await writeText(path.join(WORKSPACE, "contact-sheet-plan.txt"), [
    "1 cover, 2 problem grid, 3 objectives split, 4 literature gap columns, 5 RAD flow, 6 architecture map, 7 module lanes, 8 booking sequence, 9 implementation stack, 10 mobile UI sketch, 11 evaluation bars, 12 conclusion/recommendations split.",
  ].join("\n"));

  await writeText(path.join(SLIDES, "common.mjs"), common);
  for (let i = 1; i <= 12; i += 1) {
    const n = String(i).padStart(2, "0");
    await writeText(
      path.join(SLIDES, `slide-${n}.mjs`),
      `import { renderSlide } from "./common.mjs";\nexport async function slide${n}(presentation, ctx) { return renderSlide(presentation, ctx, ${i}); }\n`,
    );
  }

  console.log(JSON.stringify({ workspace: WORKSPACE, slides: SLIDES, preview: PREVIEW, layout: LAYOUT, qa: QA, output: OUTPUT }, null, 2));
}

main().catch((error) => {
  console.error(error.stack || error.message || String(error));
  process.exit(1);
});
