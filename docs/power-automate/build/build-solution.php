<?php

/**
 * Membangun SATU Dataverse solution (unmanaged) berisi ketiga cloud flow.
 *
 * Dipakai karena tenant PT Eclectic mengaktifkan "Create in Dataverse
 * solutions", yang membuat importer legacy package menolak zip berisi flow.
 *
 * Struktur solution:
 *   [Content_Types].xml
 *   solution.xml          -> manifest solution + publisher + daftar RootComponent
 *   customizations.xml    -> metadata tiap workflow (menunjuk ke berkas JSON-nya)
 *   Workflows/<Nama>-<GUID>.json  -> definisi flow (trigger + actions)
 *
 * Jalankan dari root repo:
 *   php docs/power-automate/build/build-solution.php
 */

require __DIR__ . '/definitions.php';

// Variabel, bukan const: heredoc PHP hanya menginterpolasi variabel.
$solutionUniqueName = 'EcoSystemTeamsIntegration';
$solutionDisplay    = 'EcoSystem Teams Integration';
$solutionVersion    = '1.0.0.0';

$publisherUnique = 'eclecticconsulting';
$publisherName   = 'PT Eclectic Consulting';
$publisherPrefix = 'eclc';

$outRoot  = 'docs/power-automate/solution-src';
$zipPath  = 'docs/power-automate/EcoSystem-Teams-Integration_1_0_0_0.zip';
$flows    = ecosystemFlows();

// Bersihkan hasil build sebelumnya agar tidak ada berkas yatim ikut terkemas.
if (is_dir($outRoot)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($outRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($outRoot);
}

// ─── Workflows/*.json ────────────────────────────────────────────────────────

/**
 * Berkas flow di dalam solution memakai pembungkus properties.definition, dan
 * connectionReferences bergaya solution ("runtimeSource": "embedded") — berbeda
 * dari legacy package yang memakai gaya "source": "Embedded".
 */
$workflowFiles = [];

foreach ($flows as $flow) {
    $guidUpper = strtoupper($flow['flowGuid']);
    $fileName  = "{$flow['schemaName']}-{$guidUpper}.json";

    put("$outRoot/Workflows/$fileName", j([
        'properties' => [
            'connectionReferences' => [
                $flow['connName'] => [
                    'runtimeSource' => 'embedded',
                    'connection'    => new stdClass(),
                    'api'           => ['name' => $flow['connName']],
                ],
            ],
            'definition' => $flow['definition'],
        ],
        'schemaVersion' => '1.0.0.0',
    ]));

    $workflowFiles[$flow['flowGuid']] = $fileName;
}

// ─── [Content_Types].xml ─────────────────────────────────────────────────────

put("$outRoot/[Content_Types].xml", <<<XML
<?xml version="1.0" encoding="utf-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="xml" ContentType="text/xml" />
  <Default Extension="json" ContentType="application/octet-stream" />
</Types>
XML);

// ─── solution.xml ────────────────────────────────────────────────────────────

$rootComponents = '';
foreach ($flows as $flow) {
    // type 29 = Workflow (cloud flow termasuk di dalamnya).
    $rootComponents .= '      <RootComponent type="29" id="{' . $flow['flowGuid'] . '}" behavior="0" />' . "\n";
}
$rootComponents = rtrim($rootComponents, "\n");

$solutionDescription = 'Tiga cloud flow integrasi EcoSystem: greeting email masuk, notifikasi tiket divalidasi, dan reminder tiket berstatus Open.';

put("$outRoot/solution.xml", <<<XML
<?xml version="1.0" encoding="utf-8"?>
<ImportExportXml version="9.2.0.0" SolutionPackageVersion="9.2" languagecode="1033" generatedBy="EcoSystem" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <SolutionManifest>
    <UniqueName>{$solutionUniqueName}</UniqueName>
    <LocalizedNames>
      <LocalizedName description="{$solutionDisplay}" languagecode="1033" />
    </LocalizedNames>
    <Descriptions>
      <Description description="{$solutionDescription}" languagecode="1033" />
    </Descriptions>
    <Version>{$solutionVersion}</Version>
    <Managed>0</Managed>
    <Publisher>
      <UniqueName>{$publisherUnique}</UniqueName>
      <LocalizedNames>
        <LocalizedName description="{$publisherName}" languagecode="1033" />
      </LocalizedNames>
      <Descriptions>
        <Description description="{$publisherName}" languagecode="1033" />
      </Descriptions>
      <EMailAddress xsi:nil="true"></EMailAddress>
      <SupportingWebsiteUrl xsi:nil="true"></SupportingWebsiteUrl>
      <CustomizationPrefix>{$publisherPrefix}</CustomizationPrefix>
      <CustomizationOptionValuePrefix>10000</CustomizationOptionValuePrefix>
      <Addresses>
        <Address>
          <AddressNumber>1</AddressNumber>
          <AddressTypeCode>1</AddressTypeCode>
          <City xsi:nil="true"></City>
          <County xsi:nil="true"></County>
          <Country xsi:nil="true"></Country>
          <Fax xsi:nil="true"></Fax>
          <FreightTermsCode xsi:nil="true"></FreightTermsCode>
          <ImportSequenceNumber xsi:nil="true"></ImportSequenceNumber>
          <Latitude xsi:nil="true"></Latitude>
          <Line1 xsi:nil="true"></Line1>
          <Line2 xsi:nil="true"></Line2>
          <Line3 xsi:nil="true"></Line3>
          <Longitude xsi:nil="true"></Longitude>
          <Name xsi:nil="true"></Name>
          <PostalCode xsi:nil="true"></PostalCode>
          <PostOfficeBox xsi:nil="true"></PostOfficeBox>
          <PrimaryContactName xsi:nil="true"></PrimaryContactName>
          <ShippingMethodCode>1</ShippingMethodCode>
          <StateOrProvince xsi:nil="true"></StateOrProvince>
          <Telephone1 xsi:nil="true"></Telephone1>
          <Telephone2 xsi:nil="true"></Telephone2>
          <Telephone3 xsi:nil="true"></Telephone3>
          <TimeZoneRuleVersionNumber xsi:nil="true"></TimeZoneRuleVersionNumber>
          <UPSZone xsi:nil="true"></UPSZone>
          <UTCOffset xsi:nil="true"></UTCOffset>
          <UTCConversionTimeZoneCode xsi:nil="true"></UTCConversionTimeZoneCode>
        </Address>
        <Address>
          <AddressNumber>2</AddressNumber>
          <AddressTypeCode xsi:nil="true"></AddressTypeCode>
          <City xsi:nil="true"></City>
          <County xsi:nil="true"></County>
          <Country xsi:nil="true"></Country>
          <Fax xsi:nil="true"></Fax>
          <FreightTermsCode xsi:nil="true"></FreightTermsCode>
          <ImportSequenceNumber xsi:nil="true"></ImportSequenceNumber>
          <Latitude xsi:nil="true"></Latitude>
          <Line1 xsi:nil="true"></Line1>
          <Line2 xsi:nil="true"></Line2>
          <Line3 xsi:nil="true"></Line3>
          <Longitude xsi:nil="true"></Longitude>
          <Name xsi:nil="true"></Name>
          <PostalCode xsi:nil="true"></PostalCode>
          <PostOfficeBox xsi:nil="true"></PostOfficeBox>
          <PrimaryContactName xsi:nil="true"></PrimaryContactName>
          <ShippingMethodCode>1</ShippingMethodCode>
          <StateOrProvince xsi:nil="true"></StateOrProvince>
          <Telephone1 xsi:nil="true"></Telephone1>
          <Telephone2 xsi:nil="true"></Telephone2>
          <Telephone3 xsi:nil="true"></Telephone3>
          <TimeZoneRuleVersionNumber xsi:nil="true"></TimeZoneRuleVersionNumber>
          <UPSZone xsi:nil="true"></UPSZone>
          <UTCOffset xsi:nil="true"></UTCOffset>
          <UTCConversionTimeZoneCode xsi:nil="true"></UTCConversionTimeZoneCode>
        </Address>
      </Addresses>
    </Publisher>
    <RootComponents>
{$rootComponents}
    </RootComponents>
    <MissingDependencies></MissingDependencies>
  </SolutionManifest>
</ImportExportXml>
XML);

// ─── customizations.xml ──────────────────────────────────────────────────────

$workflowXml = '';
foreach ($flows as $flow) {
    $guidUpper = strtoupper($flow['flowGuid']);
    $fileName  = $workflowFiles[$flow['flowGuid']];
    $name      = htmlspecialchars($flow['displayName'], ENT_XML1 | ENT_QUOTES, 'UTF-8');

    // Category 5 = Modern Flow (cloud flow). Scope 4 = Global.
    // StateCode 0 / StatusCode 1 = Draft: flow masuk dalam keadaan MATI, supaya
    // secret & Team/Channel bisa diisi dulu sebelum ada pesan terkirim.
    $workflowXml .= <<<XML
    <Workflow WorkflowId="{{$guidUpper}}" Name="{$name}">
      <JsonFileName>/Workflows/{$fileName}</JsonFileName>
      <Type>1</Type>
      <Subprocess>0</Subprocess>
      <Category>5</Category>
      <Mode>0</Mode>
      <Scope>4</Scope>
      <OnDemand>0</OnDemand>
      <TriggerOnCreate>0</TriggerOnCreate>
      <TriggerOnDelete>0</TriggerOnDelete>
      <AsyncAutodelete>0</AsyncAutodelete>
      <SyncWorkflowLogOnFailure>0</SyncWorkflowLogOnFailure>
      <StateCode>0</StateCode>
      <StatusCode>1</StatusCode>
      <RunAs>1</RunAs>
      <IsTransacted>1</IsTransacted>
      <IntroducedVersion>1.0</IntroducedVersion>
      <IsCustomizable>1</IsCustomizable>
      <BusinessProcessType>0</BusinessProcessType>
      <IsCustomProcessingStepAllowedForOtherPublishers>1</IsCustomProcessingStepAllowedForOtherPublishers>
      <PrimaryEntity>none</PrimaryEntity>
      <LocalizedNames>
        <LocalizedName languagecode="1033" description="{$name}" />
      </LocalizedNames>
    </Workflow>

XML;
}
$workflowXml = rtrim($workflowXml, "\n");

put("$outRoot/customizations.xml", <<<XML
<?xml version="1.0" encoding="utf-8"?>
<ImportExportXml xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <Entities></Entities>
  <Roles></Roles>
  <Workflows>
{$workflowXml}
  </Workflows>
  <FieldSecurityProfiles></FieldSecurityProfiles>
  <Templates></Templates>
  <EntityMaps></EntityMaps>
  <EntityRelationships></EntityRelationships>
  <OrganizationSettings></OrganizationSettings>
  <optionsets></optionsets>
  <CustomControls></CustomControls>
  <EntityDataProviders></EntityDataProviders>
  <Languages>
    <Language>1033</Language>
  </Languages>
</ImportExportXml>
XML);

// ─── Kemas ───────────────────────────────────────────────────────────────────

$count = zipDirectory($outRoot, $zipPath);
echo "OK  $zipPath ($count berkas)\n";
foreach ($flows as $flow) {
    echo "    - {$flow['displayName']}\n";
}
