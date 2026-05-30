---
name: rfc-writer
description: Writes RFC (Request for Comments) documents using a standard structure — TL;DR, WWH context, components, acceptance criteria, addenda
enabled: true
---

# RFC Writer

Write RFC documents using a fixed structure. An RFC is a concise yet comprehensive document describing a proposed change, feature, or architectural decision.

- save RFC to docs/rfc/ with a timestamped filename
- use a standard structure with sections: TL;DR, Context (WWH), Components & Specifics, Acceptance Criteria, Addenda
- ensure acceptance criteria are measurable and binary (yes/no)
- include architectural decisions, constraints, and out-of-scope items in the Components section

## RFC Structure

### 1. TL;DR
A short summary — **1–2 sentences**. The essence of the proposal: what we're doing and why. The reader should grasp the idea in 10 seconds.

### 2. Context (WWH)
Three mandatory questions as sub headings:

- **What?** — what exactly is being proposed. A specific change, feature, or refactoring.
- **Why?** — what problem does it solve, what value does it bring. Business justification or technical necessity.
- **How?** — the general implementation approach. No details — only key technical decisions and architectural choices.

### 3. Components & Specifics
Details:

- List of components/modules affected by the change
- Key architectural decisions
- Constraints and edge cases
- What is explicitly **out of scope**
- Dependencies on other tasks/components

### 4. Acceptance Criteria (DoD)
A Markdown checklist:

```markdown
- [ ] Criterion 1
- [ ] Criterion 2
```

Each item must be a concrete, verifiable statement. What needs to be done/working for the task to be considered complete.

### 5. Addenda (optional)
Supplementary materials:

- Mockups / screenshots (links or inline)
- DB schemas (Mermaid ER diagrams)
- Sequence diagrams (Mermaid)
- Roadmap / phased rollout plan (Mermaid Gantt or timeline)
- Links to related RFCs / tickets
- References to external documentation

## Writing Principles

- **Brevity** — an RFC should not exceed 1–2 pages of text. If it needs more, something's off.
- **Specificity** — no fluff. Every section answers its question.
- **Measurable DoD** — criteria must be binary (yes/no), no "partially done."
- **Explicit scope boundaries** — what we do and what we DON'T do must be obvious.

## Output Format

When writing an RFC, use Markdown with headings:

```markdown
# RFC: [Title]

## TL;DR
...

## Context
### What?
### Why?
### How?

## Components & Specifics
...

## Acceptance Criteria
- [ ] ...
- [ ] ...

## Addenda
...
```
