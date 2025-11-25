<?php

declare(strict_types=1);

namespace App\DTO\Schema;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Schema.org SoftwareRequirements DTO with IREB-compliant attributes
 * 
 * Represents functional and non-functional requirements extracted from documents.
 * Enhanced with IREB (International Requirements Engineering Board) standard attributes
 * for professional requirements management.
 * 
 * @see https://schema.org/SoftwareApplication (requirements property)
 * @see https://www.ireb.org/ (IREB Requirements Engineering)
 */
class SoftwareRequirements
{
    // ============================================
    // Core Identification (Schema.org + IREB)
    // ============================================
    
    #[ApiProperty(
        description: 'Eindeutige Identifikation des Requirements (z.B. REQ-001)',
        example: 'REQ-001'
    )]
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $identifier;

    #[ApiProperty(
        description: 'Kurzer, prägnanter Titel des Requirements',
        example: 'Benutzer muss sich mit E-Mail und Passwort anmelden können'
    )]
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $name;

    #[ApiProperty(
        description: 'Detaillierte Beschreibung des Requirements',
        example: 'Das System muss eine Login-Funktion bereitstellen, bei der sich Benutzer mit ihrer E-Mail-Adresse und einem Passwort authentifizieren können.'
    )]
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $description;

    // ============================================
    // Classification (Schema.org + IREB)
    // ============================================
    
    #[ApiProperty(
        description: 'Typ des Requirements (funktional, nicht-funktional, etc.)',
        openapiContext: [
            'type' => 'string',
            'enum' => ['functional', 'non-functional', 'technical', 'business', 'security', 'performance', 'usability', 'data', 'interface', 'quality', 'constraint', 'other']
        ],
        example: 'functional'
    )]
    #[Assert\Choice(choices: ['functional', 'non-functional', 'technical', 'business', 'security', 'performance', 'usability', 'data', 'interface', 'quality', 'constraint', 'other'])]
    public string $requirementType = 'functional';

    #[ApiProperty(
        description: 'Priorität nach MoSCoW-Methode: Must (kritisch), Should (wichtig), Could (optional), Won\'t (nicht in dieser Version)',
        openapiContext: [
            'type' => 'string',
            'enum' => ['must', 'should', 'could', 'wont']
        ],
        example: 'must'
    )]
    #[Assert\Choice(choices: ['must', 'should', 'could', 'wont'])]
    public string $priority = 'should';

    #[ApiProperty(
        description: 'Fachliche Kategorie oder Modul (z.B. "Authentifizierung", "Reporting")',
        example: 'Authentifizierung'
    )]
    #[Assert\Type('string')]
    public ?string $category = null;

    #[ApiProperty(
        description: 'Schlagwörter für Filterung und Suche',
        openapiContext: ['type' => 'array', 'items' => ['type' => 'string']],
        example: ['login', 'security', 'user-management']
    )]
    public array $tags = [];

    // ============================================
    // IREB: Rationale & Justification
    // ============================================
    
    #[ApiProperty(
        description: '🎯 IREB: Begründung/Zweck - Erklärt WARUM dieses Requirement existiert und welches Problem es löst',
        example: 'Benutzer benötigen einen sicheren Zugang zum System, um personalisierte Daten zu schützen und regulatorische Anforderungen (DSGVO) zu erfüllen.'
    )]
    #[Assert\Type('string')]
    public ?string $rationale = null;

    // ============================================
    // IREB: Lifecycle & Status Management
    // ============================================
    
    #[ApiProperty(
        description: '📋 IREB: Lifecycle-Status des Requirements im Entwicklungsprozess',
        openapiContext: [
            'type' => 'string',
            'enum' => ['draft', 'proposed', 'approved', 'implemented', 'verified', 'rejected', 'obsolete']
        ],
        example: 'approved'
    )]
    #[Assert\Choice(choices: ['draft', 'proposed', 'approved', 'implemented', 'verified', 'rejected', 'obsolete'])]
    public string $status = 'draft';

    #[ApiProperty(
        description: '🔢 Versionsnummer des Requirements (z.B. 1.0, 2.1)',
        example: '1.0'
    )]
    #[Assert\Type('string')]
    public ?string $version = '1.0';

    // ============================================
    // IREB: Stakeholder & Responsibility
    // ============================================
    
    #[ApiProperty(
        description: '👤 IREB: Verantwortlicher Stakeholder für dieses Requirement',
        example: 'Max Mustermann (Product Owner)'
    )]
    #[Assert\Type('string')]
    public ?string $stakeholder = null;

    #[ApiProperty(
        description: '✍️ IREB: Ersteller/Autor des Requirements',
        example: 'Anna Schmidt (Requirements Engineer)'
    )]
    #[Assert\Type('string')]
    public ?string $author = null;

    #[ApiProperty(
        description: '👥 IREB: Liste aller beteiligten Stakeholder',
        openapiContext: ['type' => 'array', 'items' => ['type' => 'string']],
        example: ['Product Owner', 'UX Designer', 'Security Team', 'End Users']
    )]
    public array $involvedStakeholders = [];

    // ============================================
    // IREB: Verification & Validation
    // ============================================
    
    #[ApiProperty(
        description: '✅ Akzeptanzkriterien - Messbare Bedingungen, die erfüllt sein müssen',
        example: 'Given: Benutzer auf Login-Seite | When: Gültige Credentials eingegeben | Then: Zugriff auf Dashboard gewährt'
    )]
    #[Assert\Type('string')]
    public ?string $acceptanceCriteria = null;

    #[ApiProperty(
        description: '🔍 IREB: Verifikationsmethode - Wie wird geprüft, ob das Requirement korrekt umgesetzt wurde?',
        openapiContext: [
            'type' => 'string',
            'enum' => ['test', 'inspection', 'analysis', 'demonstration', 'review', 'other']
        ],
        example: 'test'
    )]
    #[Assert\Choice(choices: ['test', 'inspection', 'analysis', 'demonstration', 'review', 'other'])]
    public ?string $verificationMethod = null;

    #[ApiProperty(
        description: '✔️ IREB: Validierungskriterien - Wie wird bestätigt, dass das Requirement das richtige Problem löst?',
        example: 'Benutzer können sich erfolgreich anmelden und 95% sind mit der UX zufrieden (User Acceptance Test)'
    )]
    #[Assert\Type('string')]
    public ?string $validationCriteria = null;

    // ============================================
    // IREB: Context & Dependencies
    // ============================================
    
    #[ApiProperty(
        description: '📄 Quelle des Requirements (Dokument, Stakeholder, Interview, etc.)',
        example: 'User Interview Session 2024-03-15, Lastenheft Kapitel 3.2'
    )]
    #[Assert\Type('string')]
    public ?string $source = null;

    #[ApiProperty(
        description: '⚠️ IREB: Randbedingungen/Einschränkungen - Technische oder organisatorische Grenzen',
        openapiContext: ['type' => 'array', 'items' => ['type' => 'string']],
        example: ['Muss DSGVO-konform sein', 'Max. Response-Zeit 2 Sekunden', 'Kompatibel mit Chrome, Firefox, Safari']
    )]
    public array $constraints = [];

    #[ApiProperty(
        description: '💭 IREB: Annahmen - Was wird vorausgesetzt?',
        openapiContext: ['type' => 'array', 'items' => ['type' => 'string']],
        example: ['Benutzer haben eine gültige E-Mail-Adresse', 'HTTPS ist verfügbar', 'Datenbank ist erreichbar']
    )]
    public array $assumptions = [];

    #[ApiProperty(
        description: '🔗 Zugehörige Requirements (IDs)',
        openapiContext: ['type' => 'array', 'items' => ['type' => 'string']],
        example: ['REQ-002', 'REQ-015', 'REQ-042']
    )]
    public array $relatedRequirements = [];

    #[ApiProperty(
        description: '🔄 IREB: Abhängigkeiten zu anderen Requirements',
        openapiContext: [
            'type' => 'object',
            'properties' => [
                'dependsOn' => ['type' => 'array', 'items' => ['type' => 'string']],
                'conflicts' => ['type' => 'array', 'items' => ['type' => 'string']],
                'extends' => ['type' => 'array', 'items' => ['type' => 'string']]
            ]
        ],
        example: ['dependsOn' => ['REQ-001', 'REQ-002'], 'conflicts' => ['REQ-010']]
    )]
    public array $dependencies = [];

    // ============================================
    // IREB: Risk Management
    // ============================================
    
    #[ApiProperty(
        description: '⚡ IREB: Zugehörige Risiken bei Nicht-Umsetzung oder falscher Implementierung',
        openapiContext: ['type' => 'array', 'items' => ['type' => 'string']],
        example: ['Datenschutzverletzung möglich', 'Unbefugter Zugriff auf sensible Daten', 'Reputationsschaden']
    )]
    public array $risks = [];

    #[ApiProperty(
        description: '🚨 IREB: Risikobewertung für dieses Requirement',
        openapiContext: [
            'type' => 'string',
            'enum' => ['low', 'medium', 'high', 'critical', 'none']
        ],
        example: 'high'
    )]
    #[Assert\Choice(choices: ['low', 'medium', 'high', 'critical', 'none'])]
    public string $riskLevel = 'none';

    // ============================================
    // IREB: Effort & Planning
    // ============================================
    
    #[ApiProperty(
        description: '⏱️ IREB: Geschätzter Aufwand (Story Points, Stunden, Personentage, etc.)',
        example: '8 Story Points (ca. 2-3 Tage)'
    )]
    #[Assert\Type('string')]
    public ?string $estimatedEffort = null;

    #[ApiProperty(
        description: '📊 IREB: Tatsächlicher Aufwand nach Implementierung',
        example: '10 Story Points (3.5 Tage)'
    )]
    #[Assert\Type('string')]
    public ?string $actualEffort = null;

    // ============================================
    // IREB: Traceability
    // ============================================
    
    #[ApiProperty(
        description: '🎯 IREB: Rückverfolgbarkeit - Welche Business-Ziele, Use Cases oder Stakeholder-Bedürfnisse werden erfüllt?',
        example: 'BZ-001: Kundenzufriedenheit erhöhen | UC-003: Benutzer authentifizieren'
    )]
    #[Assert\Type('string')]
    public ?string $traceabilityTo = null;

    #[ApiProperty(
        description: '🔨 IREB: Vorwärts-Verfolgbarkeit - Welche Implementierungen, Tests oder Design-Elemente leiten sich ab?',
        example: 'AUTH-Service | LoginController | TestCase-042, TestCase-043 | UI-Component: LoginForm'
    )]
    #[Assert\Type('string')]
    public ?string $traceabilityFrom = null;

    // ============================================
    // Timestamps
    // ============================================
    
    #[ApiProperty(
        description: '📅 Zeitstempel der Erstellung',
        writable: false
    )]
    public ?\DateTimeImmutable $createdAt = null;
    
    #[ApiProperty(
        description: '📅 Zeitstempel der letzten Änderung',
        writable: false
    )]
    public ?\DateTimeImmutable $modifiedAt = null;
    
    #[ApiProperty(
        description: '📅 Zeitstempel der Genehmigung',
        writable: false
    )]
    public ?\DateTimeImmutable $approvedAt = null;

    public function __construct(
        string $identifier,
        string $name,
        string $description,
        string $requirementType = 'functional',
        string $priority = 'should',
        string $status = 'draft'
    ) {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->description = $description;
        $this->requirementType = $requirementType;
        $this->priority = $priority;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
        $this->modifiedAt = new \DateTimeImmutable();
    }

    /**
     * Convert to array for JSON encoding (Schema.org + IREB extended)
     */
    public function toArray(): array
    {
        return [
            '@type' => 'SoftwareRequirement',
            
            // Core
            'identifier' => $this->identifier,
            'name' => $this->name,
            'description' => $this->description,
            
            // Classification
            'requirementType' => $this->requirementType,
            'priority' => $this->priority,
            'category' => $this->category,
            'tags' => $this->tags,
            
            // IREB: Rationale
            'rationale' => $this->rationale,
            
            // IREB: Lifecycle
            'status' => $this->status,
            'version' => $this->version,
            
            // IREB: Stakeholder
            'stakeholder' => $this->stakeholder,
            'author' => $this->author,
            'involvedStakeholders' => $this->involvedStakeholders,
            
            // IREB: Verification
            'acceptanceCriteria' => $this->acceptanceCriteria,
            'verificationMethod' => $this->verificationMethod,
            'validationCriteria' => $this->validationCriteria,
            
            // IREB: Context
            'source' => $this->source,
            'constraints' => $this->constraints,
            'assumptions' => $this->assumptions,
            'relatedRequirements' => $this->relatedRequirements,
            'dependencies' => $this->dependencies,
            
            // IREB: Risk
            'risks' => $this->risks,
            'riskLevel' => $this->riskLevel,
            
            // IREB: Effort
            'estimatedEffort' => $this->estimatedEffort,
            'actualEffort' => $this->actualEffort,
            
            // IREB: Traceability
            'traceabilityTo' => $this->traceabilityTo,
            'traceabilityFrom' => $this->traceabilityFrom,
            
            // Timestamps
            'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
            'modifiedAt' => $this->modifiedAt?->format(\DateTimeInterface::ATOM),
            'approvedAt' => $this->approvedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Create from LLM response array
     * 
     * Supports both minimal (LLM extracted) and complete (IREB-compliant) data
     */
    public static function fromArray(array $data): self
    {
        $requirement = new self(
            $data['identifier'] ?? uniqid('req_'),
            $data['name'] ?? '',
            $data['description'] ?? '',
            $data['requirementType'] ?? 'functional',
            $data['priority'] ?? 'should',
            $data['status'] ?? 'draft'
        );

        // Classification
        $requirement->category = $data['category'] ?? null;
        // Normalisiere tags: LLM gibt manchmal String statt Array
        $tags = $data['tags'] ?? [];
        if (is_string($tags)) {
            // Wenn tags ein String ist, entweder leeres Array oder als Array mit einem Element
            $tags = empty($tags) || $tags === '' ? [] : [$tags];
        }
        $requirement->tags = is_array($tags) ? $tags : [];
        
        // IREB: Rationale
        $requirement->rationale = $data['rationale'] ?? null;
        
        // IREB: Lifecycle
        $requirement->version = $data['version'] ?? '1.0';
        
        // IREB: Stakeholder
        $requirement->stakeholder = $data['stakeholder'] ?? null;
        $requirement->author = $data['author'] ?? null;
        $involvedStakeholders = $data['involvedStakeholders'] ?? [];
        $requirement->involvedStakeholders = is_array($involvedStakeholders) ? $involvedStakeholders : [];
        
        // IREB: Verification
        $requirement->acceptanceCriteria = $data['acceptanceCriteria'] ?? null;
        $requirement->verificationMethod = $data['verificationMethod'] ?? null;
        $requirement->validationCriteria = $data['validationCriteria'] ?? null;
        
        // IREB: Context
        $requirement->source = $data['source'] ?? null;
        $constraints = $data['constraints'] ?? [];
        $requirement->constraints = is_array($constraints) ? $constraints : [];
        $assumptions = $data['assumptions'] ?? [];
        $requirement->assumptions = is_array($assumptions) ? $assumptions : [];
        $relatedRequirements = $data['relatedRequirements'] ?? [];
        $requirement->relatedRequirements = is_array($relatedRequirements) ? $relatedRequirements : [];
        $dependencies = $data['dependencies'] ?? [];
        $requirement->dependencies = is_array($dependencies) ? $dependencies : [];
        
        // IREB: Risk
        $risks = $data['risks'] ?? [];
        $requirement->risks = is_array($risks) ? $risks : [];
        $requirement->riskLevel = $data['riskLevel'] ?? 'none';
        
        // IREB: Effort
        $requirement->estimatedEffort = $data['estimatedEffort'] ?? null;
        $requirement->actualEffort = $data['actualEffort'] ?? null;
        
        // IREB: Traceability
        $requirement->traceabilityTo = $data['traceabilityTo'] ?? null;
        $requirement->traceabilityFrom = $data['traceabilityFrom'] ?? null;
        
        // Timestamps
        if (isset($data['createdAt'])) {
            $requirement->createdAt = new \DateTimeImmutable($data['createdAt']);
        }
        if (isset($data['modifiedAt'])) {
            $requirement->modifiedAt = new \DateTimeImmutable($data['modifiedAt']);
        }
        if (isset($data['approvedAt'])) {
            $requirement->approvedAt = new \DateTimeImmutable($data['approvedAt']);
        }

        return $requirement;
    }
    
    /**
     * Update timestamp on modification
     */
    public function touch(): self
    {
        $this->modifiedAt = new \DateTimeImmutable();
        return $this;
    }
    
    /**
     * Mark requirement as approved
     */
    public function approve(): self
    {
        $this->status = 'approved';
        $this->approvedAt = new \DateTimeImmutable();
        $this->touch();
        return $this;
    }
    
    /**
     * Check if requirement is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
    
    /**
     * Check if requirement is implemented
     */
    public function isImplemented(): bool
    {
        return in_array($this->status, ['implemented', 'verified']);
    }
}

