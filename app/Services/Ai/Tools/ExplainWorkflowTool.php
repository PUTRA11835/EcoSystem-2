<?php

namespace App\Services\Ai\Tools;

use App\Models\Employee;

/**
 * Static procedural knowledge about how key EcoSystem workflows work —
 * "how do I..." questions, as opposed to live-data questions (which the
 * other tools handle). Content here was extracted from the actual
 * controllers/routes and cross-checked with the team, not guessed; where
 * something in the code was genuinely ambiguous it says so explicitly
 * rather than presenting a guess as fact.
 */
class ExplainWorkflowTool implements AiTool
{
    public function definition(): array
    {
        return [
            'name' => 'explain_workflow',
            'description' => 'Explain how a specific EcoSystem business process/workflow works — the steps, who does '
                . 'each step, and where in the UI. Use this for "how do I..." / "what\'s the process for..." questions '
                . '(e.g. "how do I propose mandays", "how does a ticket get created", "how do I set up an SLA policy"). '
                . 'Do not use this for questions about specific live records — use get_tickets / get_sla_summary / '
                . 'get_delivery_projects for those instead.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'topic' => [
                        'type' => 'string',
                        'enum' => array_keys($this->topics()),
                        'description' => 'Which workflow to explain: "mandays" (both Customer Mandays and Resolution '
                            . 'Days proposals), "ticket_lifecycle" (creation, assignment, status), '
                            . '"delivery_project_planning" (project setup and phase/stage/activity planning), or '
                            . '"sla_config" (setting up SLA policies).',
                    ],
                ],
                'required' => ['topic'],
            ],
        ];
    }

    public function run(Employee $employee, array $input): array
    {
        $topic = $input['topic'] ?? null;
        $topics = $this->topics();

        if (!$topic || !isset($topics[$topic])) {
            return [
                'error' => 'Unknown topic. Valid topics: ' . implode(', ', array_keys($topics)),
            ];
        }

        return ['topic' => $topic, 'guide' => $topics[$topic]];
    }

    /**
     * @return array<string, string>
     */
    private function topics(): array
    {
        return [
            'mandays' => <<<'MD'
                EcoSystem has TWO separate mandays proposal workflows on a ticket — don't conflate them. Neither has
                its own menu page; both live inside the ticket detail page, in the "Mandays" sidebar panel.

                ## 1. Customer Mandays — the quote proposed TO the customer
                An itemized (Activity × Module × mandays) quote for one ticket, requiring the customer's approval.

                1. The ticket's PIC (or a team member) fills a line-item grid plus a required description, and saves
                   as a draft. For "Change Request" type tickets, only the ticket's team lead may do this.
                2. PIC submits the draft to Helpdesk (status becomes "pending_helpdesk").
                3. Helpdesk reviews, can edit line items/description/notes depending on their permissions, then either:
                   - Sends it to the customer (posts into the ticket chat and emails the customer), or
                   - Approves it directly on Helpdesk's own authority — sending to the customer first is NOT required.
                4. The customer approves or rejects via the Jarvies portal. Approval sets the ticket's `man_days` to
                   this proposal's total. Rejection sends it back to Helpdesk with the customer's reason attached.
                5. A pending/sent/approved proposal can be cancelled (cancelling an already-approved one needs an
                   extra permission).

                Button label in the ticket page: "Propose Mandays" (changes to a status-specific label once one exists).

                ## 2. Resolution Days — internal per-consultant allocation
                Separate from the customer quote: how many mandays are internally allocated to each employee working
                the ticket. This is what timesheet submission is checked against.

                1. The PIC (or a participant) proposes mandays per employee, with an optional "additional mandays"
                   (which requires a note explaining why).
                2. Submitted to the Head of Support/Project. For "Change Request" tickets specifically, this
                   submission is blocked until the Customer Mandays proposal above is already approved.
                3. The Head reviews and approves — filling in the actual approved mandays per employee, which can
                   differ from what was proposed. **There is no reject/revision mechanism** — the Head's only action
                   is to approve (with whatever numbers they decide on); if the numbers need to change, that happens
                   through the approved amount itself, not a separate reject step.

                Button label in the ticket page: "Propose Resolution Days" / "Review Resolution Days" (Head side).

                ## Important: how `man_days` on the ticket actually behaves
                Whichever of the two workflows above gets approved MOST RECENTLY overwrites the ticket's `man_days`
                field with its own total — the two are not added together, and neither one is permanently "the final
                word" over the other; it's simply last-approval-wins. Also worth knowing: this `man_days` field is a
                summary/display value only — it does NOT feed the MD-quota check used when employees submit
                timesheets (that check reads a different, per-allocation number directly).
                MD,

            'ticket_lifecycle' => <<<'MD'
                ## Creating a ticket
                Most tickets go through a staging step before becoming a real ticket:
                - Customer-submitted (via the Jarvies portal), inbound email, or an internal web form all create a
                  "staging ticket" first, not a live ticket directly.
                - An admin (or Delivery Support / staging-authorized role) reviews the staging ticket under
                  **Ticket → Ticket Validation**, fills in ticket type/priority/scale, and approves it — this creates
                  the real ticket with status "open". The admin can reject it instead, and no ticket is created.
                - EC Administrator can also create a ticket directly, bypassing staging entirely — a directly-created
                  ticket starts at status "inprocess" rather than "open".

                ## Assigning a PIC (ticket lead)
                Two distinct paths, used in different situations:
                - **Take Ticket**: a Delivery Support consultant who is qualified can pick up an unassigned ticket
                  themselves. This doesn't assign it immediately — it creates a pending confirmation that an admin
                  then approves or rejects. Use this when a consultant is looking for work to pick up.
                - **Direct assign**: an admin assigns a PIC directly without going through Take/confirm. Use this when
                  admin needs to push a ticket to someone (e.g. a high-priority ticket that needs immediate ownership).

                ## Status
                Ticket status is one of: open, inprocess, waiting_on_customer, waiting_on_3rd_party,
                waiting_to_confirmation, hold, cancelled, closed. Status can be changed to any other status by an
                authorized role (Ticket Manager group) — there is no fixed required sequence enforced by the system,
                so don't describe status changes as needing to follow one specific order. Closing or cancelling a
                ticket posts a system message to the ticket chat and emails the customer.
                MD,

            'delivery_project_planning' => <<<'MD'
                ## Creating a delivery project
                Menu path: **Delivery → Project**. Creating one requires: client (must be a Business Partner of type
                Customer), project owner, name, description, project type, contract start/end dates, and a unique IO
                number (Body Hire-type projects may share an IO number within the same company). Optional but common:
                role assignments (delivery owner, delivery manager, project manager, co-PM, project admin),
                financials (revenue, plan cost, gross profit), delivery method, warranty period, total mandays,
                location, and an inline team-members table. A newly created project starts at status "On Track" with
                no schedule baseline yet, since progress tracking needs planning data to compare against.

                ## Planning structure
                Planning lives under the project's **Planning** tab/section, structured as a hierarchy:
                **Phase → Group → Stage → Activity**. Phases can be added from a template or created custom, then
                reordered or hidden. Within a phase, stages are managed, and within a stage, individual activities
                (each can have its own assigned members). There are three separate permission levels for this area —
                view, edit (modify existing items), and manage (add/remove items) — so what a given user can do here
                depends on which of those they've been granted.

                Supporting views available from the planning section: a Gantt chart, an S-Curve, a data table view,
                and PDF/Excel export.
                MD,

            'sla_config' => <<<'MD'
                Menu path: **SLA → SLA Config** (a separate function from "SLA Report", which only displays data).
                Requires the `sla.config` permission, assignable per role — not tied to a specific hardcoded role.

                ## What defines an SLA policy
                A policy is uniquely identified by the combination of **Delivery Support × Priority × Scale** — not
                per-customer, even though policies end up applying to tickets under a given Delivery Support queue.
                - Priority: Low, Medium, High, Very High
                - Scale: Simple, Medium, Complex

                Each policy sets a response-time and resolution-time limit (in hours), plus optionally a working-hours
                window (start/end time, break start/end) if it should only count business hours rather than 24/7.

                ## Important rule
                **Very High priority is always forced to 24/7** — regardless of what working-hours setting is
                submitted for it, the system overrides it to count around the clock. This isn't optional or editable.

                ## Editing and deleting
                You can change a policy's hours, 24/7 flag, working-hours window, and active/inactive state — but not
                its identity (Delivery Support / Priority / Scale combination); to change those, create a new policy
                instead. A policy can't be deleted while any ticket's SLA record already references it — deactivate
                it instead (set inactive) if it shouldn't apply to new tickets going forward.
                MD,
        ];
    }
}
