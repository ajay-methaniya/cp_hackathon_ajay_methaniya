# Architecture & structure

High-level layout for **Architecture & structure** review.

```mermaid
flowchart TB
    subgraph client [Browser]
        UI[Views + Tailwind / Alpine / Chart.js]
    end
    subgraph public [public/]
        Router[index.php routes]
    end
    subgraph app [app/]
        C[Controllers]
        S[Services: Whisper, GPT, Pipeline, Reports, Storage]
        M[Models: Call, Analysis, Transcript, User]
    end
    subgraph data [Data]
        MySQL[(MySQL)]
        FS[storage/audio + logs]
    end
    UI --> Router --> C
    C --> S
    S --> M
    M --> MySQL
    S --> FS
    OpenAI[(OpenAI API)] <--> S
```

## Request flow (call analysis)

1. **POST `/calls/upload`** → `CallController::store` → `FileStorageService` → DB row `uploaded`.
2. **Shutdown handler** → `CallPipelineService::process`:
   - `WhisperService::transcribe`
   - `Transcript::upsert`
   - `GPTAnalysisService::analyzeTranscript`
   - `Analysis::saveForCall` → status `complete`.

## Key boundaries

- **Controllers** — Auth, CSRF, HTTP only.
- **Services** — External APIs and orchestration.
- **Models** — SQL via PDO prepared statements.
