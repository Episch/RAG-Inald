# 🕸️ Neo4j Graph Model - Requirements Engineering

## Überblick

Das Graph-Modell folgt **IREB-Standards** und verwendet **Relationen statt Arrays** für bessere Queryability und Traceability.

---

## 📊 Node Types

### 1. **SoftwareApplication**
Repräsentiert eine Softwareanwendung/ein Projekt.

```cypher
(:SoftwareApplication {
    nameKey: string,           // Normalisiert (lowercase) für MERGE
    name: string,              // Original name
    description: string,
    version: string,
    applicationCategory: string,
    operatingSystem: string,
    softwareVersion: string,
    license: string,
    provider: string,
    keywords: string[],
    createdAt: datetime,
    updatedAt: datetime
})
```

**Constraint**: `nameKey` ist UNIQUE

---

### 2. **SoftwareRequirement**
Ein einzelnes Requirement (funktional oder nicht-funktional).

```cypher
(:SoftwareRequirement {
    identifier: string,          // UNIQUE (z.B. "REQ-001")
    name: string,
    description: string,
    requirementType: string,     // functional, non-functional, etc.
    priority: string,            // must, should, could, wont
    category: string,
    tags: string[],
    status: string,              // draft, approved, implemented, etc.
    version: string,             // Auto-increment bei MERGE (1.0, 1.1, 1.2...)
    
    // IREB: Rationale
    rationale: string,
    source: string,
    
    // IREB: Verification
    acceptanceCriteria: string,
    verificationMethod: string,
    validationCriteria: string,
    
    // IREB: Stakeholder (Hauptverantwortlicher)
    stakeholder: string,
    author: string,
    
    // IREB: Risk
    riskLevel: string,           // none, low, medium, high, critical
    
    // IREB: Effort
    estimatedEffort: string,
    actualEffort: string,
    
    // IREB: Traceability
    traceabilityTo: string,
    traceabilityFrom: string,
    
    // Semantic Search
    embedding: float[],          // Vector embedding für similarity search
    
    createdAt: datetime,
    updatedAt: datetime
})
```

**Constraint**: `identifier` ist UNIQUE

**Versioning**: Bei jedem Update (MERGE ON MATCH) wird `version` automatisch um 0.1 erhöht.

---

### 3. **Person** 👤
Repräsentiert einen Stakeholder/Autor.

```cypher
(:Person {
    name: string,              // MERGE key
    createdAt: datetime,
    updatedAt: datetime
})
```

**Verwendung**: Eine Person kann Stakeholder für mehrere Requirements sein (M:N Relation).

---

### 4. **Risk** ⚠️
Ein identifiziertes Risiko für ein Requirement.

```cypher
(:Risk {
    description: string,
    severity: string,          // low, medium, high, critical
    probability: string,       // optional: Eintrittswahrscheinlichkeit
    impact: string,            // optional: Auswirkung
    mitigation: string,        // optional: Maßnahmen zur Risikominderung
    createdAt: datetime
})
```

**Verwendung**: Ein Requirement kann mehrere Risks haben (1:N Relation).

---

### 5. **Constraint** 🔒
Eine Einschränkung/Nebenbedingung für ein Requirement.

```cypher
(:Constraint {
    description: string,
    type: string,              // technical, legal, budget, time, etc.
    createdAt: datetime
})
```

**Verwendung**: Ein Requirement kann mehrere Constraints haben (1:N Relation).

---

### 6. **Assumption** 💭
Eine Annahme die für ein Requirement getroffen wurde.

```cypher
(:Assumption {
    description: string,
    validated: boolean,        // Wurde die Annahme validiert?
    createdAt: datetime
})
```

**Verwendung**: Ein Requirement kann mehrere Assumptions haben (1:N Relation).

---

## 🔗 Relationship Types

### 1. **HAS_REQUIREMENT**
Application → Requirement

```cypher
(:SoftwareApplication)-[:HAS_REQUIREMENT]->(:SoftwareRequirement)
```

Ein Projekt hat mehrere Requirements.

---

### 2. **STAKEHOLDER** 👥
Requirement → Person

```cypher
(:SoftwareRequirement)-[:STAKEHOLDER {role: string, createdAt: datetime}]->(:Person)
```

**Properties**:
- `role`: "author" (Ersteller) oder "stakeholder" (Beteiligter)

Ein Requirement kann mehrere Stakeholder haben, eine Person kann an mehreren Requirements beteiligt sein (M:N).

---

### 3. **HAS_RISK** ⚠️
Requirement → Risk

```cypher
(:SoftwareRequirement)-[:HAS_RISK {identifiedAt: datetime}]->(:Risk)
```

Ein Requirement kann mehrere Risks haben (1:N).

---

### 4. **HAS_CONSTRAINT** 🔒
Requirement → Constraint

```cypher
(:SoftwareRequirement)-[:HAS_CONSTRAINT]->(:Constraint)
```

Ein Requirement kann mehrere Constraints haben (1:N).

---

### 5. **HAS_ASSUMPTION** 💭
Requirement → Assumption

```cypher
(:SoftwareRequirement)-[:HAS_ASSUMPTION]->(:Assumption)
```

Ein Requirement kann mehrere Assumptions haben (1:N).

---

### 6. **RELATED_TO** 🔗
Requirement → Requirement

```cypher
(:SoftwareRequirement)-[:RELATED_TO {createdAt: datetime}]->(:SoftwareRequirement)
```

Generische thematische Beziehung zwischen Requirements.

---

### 7. **DEPENDS_ON** ⚙️
Requirement → Requirement

```cypher
(:SoftwareRequirement)-[:DEPENDS_ON {
    type: string,         // logical, technical, temporal
    strength: string,     // mandatory, optional
    createdAt: datetime
}]->(:SoftwareRequirement)
```

Ein Requirement hängt von einem anderen ab (z.B. "Login" → "Session Management").

---

### 8. **CONFLICTS_WITH** ⚔️
Requirement → Requirement

```cypher
(:SoftwareRequirement)-[:CONFLICTS_WITH {
    severity: string,     // low, medium, high
    resolved: boolean,
    createdAt: datetime
}]->(:SoftwareRequirement)
```

Zwei Requirements widersprechen sich.

---

### 9. **EXTENDS** ➕
Requirement → Requirement

```cypher
(:SoftwareRequirement)-[:EXTENDS {
    extensionType: string,  // optional, mandatory
    createdAt: datetime
}]->(:SoftwareRequirement)
```

Ein Requirement erweitert ein anderes (z.B. "OAuth2 Login" extends "Basic Login").

---

## 🔍 Beispiel-Queries

### Alle Risks eines Requirements finden:

```cypher
MATCH (req:SoftwareRequirement {identifier: 'REQ-001'})-[:HAS_RISK]->(risk:Risk)
RETURN risk.description, risk.severity
ORDER BY risk.severity DESC
```

### Alle Stakeholder eines Projects finden:

```cypher
MATCH (app:SoftwareApplication {name: 'E-Commerce Platform'})-[:HAS_REQUIREMENT]->(req)-[:STAKEHOLDER]->(person:Person)
RETURN DISTINCT person.name, collect(DISTINCT req.identifier) as requirements
```

### Requirements mit hohem Risiko finden:

```cypher
MATCH (req:SoftwareRequirement)-[:HAS_RISK]->(risk:Risk {severity: 'high'})
RETURN req.identifier, req.name, count(risk) as high_risk_count
ORDER BY high_risk_count DESC
```

### Requirement-Abhängigkeiten visualisieren:

```cypher
MATCH path = (req1:SoftwareRequirement)-[:DEPENDS_ON|EXTENDS|RELATED_TO*1..3]->(req2:SoftwareRequirement)
WHERE req1.identifier = 'REQ-001'
RETURN path
```

### Alle Requirements mit einem bestimmten Stakeholder:

```cypher
MATCH (person:Person {name: 'Max Mustermann'})<-[:STAKEHOLDER]-(req:SoftwareRequirement)
RETURN req.identifier, req.name, req.status
ORDER BY req.priority DESC
```

---

## 🎯 Vorteile des Graph-Modells

1. **Queryability**: Komplexe Beziehungen mit Cypher einfach abfragen
2. **Traceability**: Requirements-Abhängigkeiten visualisieren
3. **Stakeholder-Management**: Person-Nodes werden über mehrere Requirements geteilt
4. **Risk-Tracking**: Risks als eigene Entities mit Properties
5. **IREB-Compliance**: Folgt Requirements Engineering Best Practices
6. **Versionierung**: Automatisches Version-Increment bei Updates
7. **Semantic Search**: Embedding-Vektoren für AI-gestützte Suche

---

## 🔄 Migration von altem Modell

**Alte Arrays → Neue Relationen:**

| Alt (Array-Property) | Neu (Relation + Node) |
|----------------------|------------------------|
| `req.risks[]` | `(req)-[:HAS_RISK]->(Risk)` |
| `req.involvedStakeholders[]` | `(req)-[:STAKEHOLDER]->(Person)` |
| `req.constraints[]` | `(req)-[:HAS_CONSTRAINT]->(Constraint)` |
| `req.assumptions[]` | `(req)-[:HAS_ASSUMPTION]->(Assumption)` |
| `req.relatedRequirements[]` | `(req)-[:RELATED_TO]->(req2)` |

**Bei jedem Update (MERGE ON MATCH):**
- Alte Risks/Constraints/Assumptions werden gelöscht und neu erstellt
- Stakeholder-Relationen werden aktualisiert (Person-Nodes bleiben erhalten)
- Version wird automatisch erhöht

---

## 🛠️ Index-Strategie

```cypher
// Constraints (ensure uniqueness)
CREATE CONSTRAINT requirement_id_unique FOR (r:SoftwareRequirement) REQUIRE r.identifier IS UNIQUE;
CREATE CONSTRAINT app_namekey_unique FOR (a:SoftwareApplication) REQUIRE a.nameKey IS UNIQUE;

// Performance Indexes
CREATE INDEX requirement_type FOR (r:SoftwareRequirement) ON (r.requirementType);
CREATE INDEX requirement_priority FOR (r:SoftwareRequirement) ON (r.priority);
CREATE INDEX requirement_status FOR (r:SoftwareRequirement) ON (r.status);
CREATE INDEX person_name FOR (p:Person) ON (p.name);
CREATE INDEX risk_severity FOR (r:Risk) ON (r.severity);
CREATE INDEX constraint_type FOR (c:Constraint) ON (c.type);
```

---

## 📚 IREB-Compliance

Dieses Modell implementiert folgende IREB-Konzepte:

- ✅ **Rationale**: Begründung für Requirements
- ✅ **Stakeholder Management**: Person-Nodes mit Rollen
- ✅ **Risk Management**: Risk-Nodes mit Severity
- ✅ **Constraint Management**: Explicit constraints
- ✅ **Traceability**: Relations zwischen Requirements
- ✅ **Verification**: Acceptance Criteria, Verification Methods
- ✅ **Lifecycle**: Status, Version, Timestamps
- ✅ **Dependencies**: DEPENDS_ON, EXTENDS, CONFLICTS_WITH Relations

---

## 🎨 Graph Visualization

```
┌─────────────────────┐
│ SoftwareApplication │
└──────────┬──────────┘
           │ HAS_REQUIREMENT
           ▼
┌──────────────────────┐       ┌────────┐
│ SoftwareRequirement  │───────▶│ Person │
└──────────┬───────────┘  STAKEHOLDER  └────────┘
           │
           ├─── HAS_RISK ──────▶ ┌──────┐
           │                     │ Risk │
           │                     └──────┘
           │
           ├─── HAS_CONSTRAINT ─▶ ┌────────────┐
           │                      │ Constraint │
           │                      └────────────┘
           │
           ├─── HAS_ASSUMPTION ─▶ ┌────────────┐
           │                      │ Assumption │
           │                      └────────────┘
           │
           └─── RELATED_TO ─────▶ ┌──────────────────────┐
           └─── DEPENDS_ON ─────▶ │ SoftwareRequirement  │
           └─── EXTENDS ────────▶ │      (andere)        │
           └─── CONFLICTS_WITH ─▶ └──────────────────────┘
```

