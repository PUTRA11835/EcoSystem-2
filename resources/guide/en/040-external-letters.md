---
key: external-letters
chapter: external
order: 40
title: Consultant Letters
summary: Create and archive assignment letters, employment certificates and reference letters for consultants.
requires: [general.external.assignment-letters, general.external.employment-certificates, general.external.reference-letters]
updated: 2026-10-27
---
Three pages in the `External` section create PDF letters for consultants: `Assignment Letter`, `Employment Certificate` and `Reference Letter`. Each letter is archived as it was issued.

## Who uses this

HR staff who issue letters to external consultants. Each page has its own access, so you may see only some of them. The `Create Document` button appears only with the Create permission.

## Before you start

- Open `External` and choose `Assignment Letter`, `Employment Certificate` or `Reference Letter`.
- The consultant should already exist, see [[external-consultants]]. If the letter refers to an engagement, create the contract first, see [[external-contracts]].
- The signer must be registered as a signer. Only the designated signers are offered; if one is not available the page shows a warning. Ask your administrator to register that person.

## Step by step

### Read the list

1. Open one of the three pages. The cards count `All external documents` and the letters of each type.
2. Search by consultant, consultant ID, letter number or contract.
3. Read the table: `Document`, `Consultant`, `External contract`, `Issued` and `Status`.
4. Use the PDF buttons to open or download a letter.

The list notes that each generated letter is automatically archived and registered as an outgoing letter. You can also find it in the register, see [[letter-templates]].

### Create a letter

1. Select `Create Document`.
2. Under `Document info`, choose the `Document type` (it is preselected from the page you opened), the `Document date` and the `Language`.
3. Leave `Reference no.` blank for automatic numbering from the outgoing-letter sequence, or type one.
4. Enter who the letter is `Addressed to` and the purpose. The purpose field changes per type: `Assignment / purpose`, `Purpose` or `Reference summary`.
5. Under `Consultant data`, search the `External consultant` and optionally choose the `Related EXT contract`. Pick a contract when the letter must reference an active engagement period.
6. Under `Assignment & authorization`, enter the `Assignment location`, the `Assignment start` and `Assignment end`.
7. Choose the `Signer`. The signer position is filled in, and you can change what is printed under the signature.
8. Add an optional `Archive note`.
9. Select `Generate & Archive PDF`.

The PDF is generated and added to the list with a letter number and a status.

> [!IMPORTANT]
> Check the details before you generate. A letter that has been issued is archived as issued.

## What happens next

The letter appears in the list of its type and in the `Letter Register` as an outgoing letter. Open the PDF to print or send it.

## Common questions

**Can I use these letters for internal employees?** No. Employee letters are created in `Letter Templates`.

**Why is the signer list short?** Only designated signers can sign consultant letters.

## Related topics

- [[external-consultants]]
- [[external-contracts]]
- [[letter-templates]]

<!-- sources: routes/hr-external.php, app/Support/External/ExternalRules.php, config/hc_external.php, resources/views/hr-general/external/documents/index.blade.php, resources/views/hr-general/external/documents/components/list-table.blade.php, resources/views/hr-general/external/components/signer-warning.blade.php -->
