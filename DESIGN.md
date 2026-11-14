# MedCore — International Clinical Design System Specification (`DESIGN.md`)
*Compliant with the [VoltAgent/awesome-design-md](https://github.com/VoltAgent/awesome-design-md) 9-Section Specification*

---

## 1. Visual Theme & Atmosphere

MedCore is engineered as an **International Clinical Healthcare Platform & National EHR Ecosystem**, combining the clinical authority of Mayo Clinic and Cleveland Clinic with the operational ergonomics of Epic Systems and One Medical.

- **Atmosphere**: Pristine clinical sterility, unquestioned regulatory trust, calm optical clarity, and human-centered warmth.
- **Visual Metaphor**: Physical clinical dossiers, razor-thin hairline borders (`1px solid #E2E8F0`), elevated frosted glass scrims, high-contrast status chips, and dedicated portal rail architecture.
- **Density**: Clinical High-Efficiency (dense enough for doctors reviewing 20-entry consultation histories in seconds; spacious enough for patients checking their ICE emergency card on a phone in an ambulance).

---

## 2. Color Palette & Semantic Roles

### 2.1 Surface Architecture
| Token | Hex / Value | Purpose |
| :--- | :--- | :--- |
| `--mc-bg` | `#F8FAFC` | Main canvas background (Hospital Slate 50) |
| `--mc-surface` | `#FFFFFF` | Primary card, modal, and panel background |
| `--mc-surface-inset` | `#F1F5F9` | Inset data boxes, table headers, code wells |
| `--mc-surface-hover` | `#F8FAFC` | Table row hover, menu item hover |
| `--mc-line` | `#E2E8F0` | Default hairline border (`1px solid var(--mc-line)`) |
| `--mc-line-dark` | `#CBD5E1` | Interactive borders, input outlines |

### 2.2 Brand & Portals
| Role | Hex | Usage |
| :--- | :--- | :--- |
| **Primary Brand Navy** | `#0A2540` | Authority banners, header brand, primary titles |
| **Clinical Cobalt** | `#0052CC` | Primary action buttons, patient portal accent |
| **Doctor Stethoscope Teal** | `#0D9488` | Doctor clinical portal accent, prescription badge |
| **Hospital Steel Navy** | `#0B4F6C` | Hospital OPD reception, roster management accent |
| **Laboratory Indigo** | `#4F46E5` | Diagnostic lab portal, pathology test token accent |
| **Central Admin Royal** | `#1E40AF` | System administration, BMDC credential licensing |

### 2.3 Semantic Status Tokens
| Semantic Status | Text Hex | Surface Hex | Border Hex | Clinical Context |
| :--- | :--- | :--- | :--- | :--- |
| **Verified / Normal (OK)** | `#027A48` | `#ECFDF3` | `#ABEFC6` | Active BMDC license, normal lab range, approved doctor |
| **Triage / Pending (Warn)**| `#B54708` | `#FFFAEB` | `#FEDF89` | Pending hospital affiliation, waiting queue, awaiting test |
| **Critical / Emergency**   | `#B42318` | `#FEF3F2` | `#FECDCA` | Emergency ICE, severe allergy, rejected credentials, suspended |
| **Diagnostic / Info**      | `#175CD3` | `#EFF8FF` | `#B2DDFF` | Test token `LT-`, consultation ID, department chip |

### 2.4 Text Hierarchy & Ink
- **Primary Ink** (`#0F172A`): Headings, patient names, critical values.
- **Secondary Ink** (`#334155`): Explanatory body text, clinical labels, table headers.
- **Muted Ink** (`#64748B`): Timestamps, registration IDs, empty state subtext.
- **Inverted Ink** (`#FFFFFF`): Dark banners, emergency hero cards, button text.

---

## 3. Typography Rules

### 3.1 Font Stacks
- **Display & Section Titles**: `'Plus Jakarta Sans', 'Instrument Sans', system-ui, sans-serif`
- **Body & Clinical Prose**: `'Inter', system-ui, -apple-system, sans-serif`
- **Bengali Clinical Terms**: `'Hind Siliguri', 'Inter', sans-serif`
- **Clinical Identifiers & Tokens**: `'JetBrains Mono', ui-monospace, monospace` (e.g. `PT-000001`, `LT-98214`, `BMDC-A-98765`)

### 3.2 Scale & Hierarchy
| Level | Font Stack | Size | Weight | Line Height | Letter Spacing |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Display H1** | Display Stack | `clamp(2.2rem, 4vw, 3.25rem)` | 800 | 1.1 | `-0.025em` |
| **Section H2** | Display Stack | `clamp(1.5rem, 2.5vw, 2.0rem)` | 700 / 800 | 1.2 | `-0.015em` |
| **Card H3** | Display Stack | `1.15rem - 1.25rem` | 700 | 1.3 | `-0.01em` |
| **Subhead H4** | Body Stack | `0.95rem - 1.05rem` | 650 | 1.4 | `normal` |
| **Body Large** | Body Stack | `1.05rem - 1.15rem` | 400 / 500 | 1.6 | `normal` |
| **Body Text** | Body Stack | `0.92rem - 0.98rem` | 400 / 500 | 1.55 | `normal` |
| **Kicker / Eyebrow**| Display Stack | `0.72rem - 0.78rem` | 800 | 1.2 | `0.08em` uppercase |
| **Data Monospace** | Monospace Stack| `0.78rem - 0.88rem` | 700 | 1.4 | `0.04em` |

---

## 4. Component Stylings

### 4.1 Buttons
- **Primary (`.mc-btn-primary`)**: Solid brand background, white text, subtle downward press (`transform: translateY(-1px)` on hover, `0 8px 20px rgba(0,82,204,0.22)` shadow).
- **Secondary (`.mc-btn-secondary`)**: White surface, `#E2E8F0` border, `#0F172A` text, subtle hover background `#F8FAFC`.
- **Emergency (`.mc-btn-danger`)**: Crimson `#DC2626`, high-contrast white text, `0 8px 20px rgba(220,38,38,0.25)`.
- **Ghost (`.mc-btn-ghost`)**: Transparent surface, clean border or text-only, interactive background on hover.
- **Sizing**: Regular (`min-height: 42px`, `padding: 0.6rem 1.1rem`), Small (`min-height: 34px`, `padding: 0.35rem 0.75rem`), Large (`min-height: 50px`, `padding: 0.85rem 1.5rem`).

### 4.2 Clinical Panels & Cards
- **Border**: `1px solid var(--mc-line)` (`#E2E8F0`).
- **Radius**: `12px` (inner elements `8px`, modal `16px`).
- **Header (`.mc-panel-h`)**: Flex justify-between, 1.1rem H2, subtext in muted ink, top action button slot.
- **Card Shadow**: `0 1px 3px rgba(15,23,42,0.04), 0 10px 24px -4px rgba(10,37,64,0.05)`.

### 4.3 Form Inputs
- **Field Wrapper (`.mc-field`)**: Top label in 0.82rem bold text, input, optional help subtext.
- **Input / Select / Textarea**: `1px solid #CBD5E1`, radius `10px`, padding `0.75rem 0.95rem`, font-size `0.95rem`.
- **Focus State**: `border-color: #0052CC; box-shadow: 0 0 0 3px rgba(0,82,204,0.15); outline: none;`.

### 4.4 Clinical Status Pills (`.lab-status`)
- `.lab-status.ok`: Green background `#ECFDF3`, border `#ABEFC6`, text `#027A48`.
- `.lab-status.warn`: Amber background `#FFFAEB`, border `#FEDF89`, text `#B54708`.
- `.lab-status.danger`: Red background `#FEF3F2`, border `#FECDCA`, text `#B42318`.
- `.lab-status.info`: Blue background `#EFF8FF`, border `#B2DDFF`, text `#175CD3`.

### 4.5 Data Tables (`.lab-table`)
- Table Header: Uppercase, 0.75rem font size, 700 weight, color `#64748B`, background `#F8FAFC`, bottom border `2px solid #E2E8F0`.
- Cells: Vertical align middle, padding `12px 16px`, subtle bottom border `1px solid #F1F5F9`.
- Row Hover: Background `#FAFBFD`.

---

## 5. Layout Principles & Spacing Scale

### 5.1 Spacing Scale
- `4px` (`--space-1`): Fine chip padding, icon gaps.
- `8px` (`--space-2`): Input inline padding, badge margins.
- `12px` (`--space-3`): Tight card gaps, list item padding.
- `16px` (`--space-4`): Standard component padding, grid gaps.
- `24px` (`--space-6`): Panel padding, section spacing.
- `32px` (`--space-8`): Large section padding.
- `48px` - `64px` (`--space-12`, `--space-16`): Major landing page heroes and footers.

### 5.2 Container Widths
- Standard Content: `width: min(1200px, calc(100% - 2rem)); margin-inline: auto;`.
- Reading / Dossier Column: `max-width: 840px`.

---

## 6. Depth & Elevation

- **Level 0 (Flat)**: Background canvas `#F8FAFC`.
- **Level 1 (Clinical Card)**: Background `#FFFFFF`, Border `1px solid #E2E8F0`, Shadow `0 1px 3px rgba(0,0,0,0.03), 0 8px 20px -3px rgba(10,37,64,0.05)`.
- **Level 2 (Active/Hover Card)**: Shadow `0 12px 30px -4px rgba(10,37,64,0.10)`, `transform: translateY(-2px)`.
- **Level 3 (Modal / Floating Sheet)**: Background `#FFFFFF`, Shadow `0 24px 60px -12px rgba(15,23,42,0.22)`, Backdrop `rgba(15,23,42,0.55)` with `backdrop-filter: blur(4px)`.

---

## 7. Do's and Don'ts (Anti-AI Slop Guardrails)

### DO:
- Use precise clinical terminology and dual-language labels (English + Bengali) where appropriate.
- Include clear demo credential callouts with 1-click fill so testing is instantaneous.
- Ensure all IDs (`PT-`, `DOC-`, `LT-`, `CONS-`) are set in `JetBrains Mono` or monospace.
- Include active/focus/hover micro-interactions on all buttons, links, and cards.
- Show clear empty states with icons, helpful guidance, and action buttons.

### DON'T:
- NEVER use generic purple-to-blue AI gradients.
- NEVER use unpadded, raw black `#000000` text on pure white.
- NEVER leave input fields without focus rings.
- NEVER hide or omit any of the 5 portals (Patient, Doctor, Hospital, Lab, Central Admin).
- NEVER introduce unstyled raw tables without header borders or row hover highlights.

---

## 8. Responsive Behavior & Mobile Ergonomics

- **Desktop (`>= 960px`)**: Full portal rail visible (240px wide), full top navigation with links and quick action CTAs.
- **Tablet (`640px - 959px`)**: 2-column card layouts, responsive form grids, collapsible portal drawer.
- **Mobile (`< 640px`)**: Single-column vertical stacks, sticky header with menu toggle `#mcRailToggle` / `#mc-menu-btn`, touch target minimum `44px`, horizontal scroll on wide data tables with `.table-responsive`.

---

## 9. Agent Prompt Guide & Quick Reference

When creating any new page or modifying an existing view in MedCore:
1. Wrap page contents in `portal_shell_start.php` + `portal_shell_end.php` (for portals) or `head.php` + `public_header.php` + `public_footer.php` (for public pages).
2. Use `.mc-panel` for cards, `.mc-panel-h` for headers, `.lab-table` for tabular data, `.lab-status` for status pills, and `.mc-btn-*` for actions.
3. Keep color accents synchronized with the portal role (`#0052CC` Patient, `#0D9488` Doctor, `#0B4F6C` Hospital, `#4F46E5` Lab, `#1E40AF` Admin).
