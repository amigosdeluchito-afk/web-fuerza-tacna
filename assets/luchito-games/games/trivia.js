window.LuchitoGames.registerGame('trivia', {
    title: 'Trivia Tacneña',
    render: function(container, level = 'medium') {
        const styleId = 'luchito-trivia-styles';
        if (!document.getElementById(styleId)) {
            const style = document.createElement('style');
            style.id = styleId;
            style.innerHTML = `
                .lg-trivia-game { font-family: system-ui, -apple-system, sans-serif; }
                .lg-trivia-header { width: 100%; display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: bold; color: #801039; }
                .lg-trivia-progress { background: #fdf5f7; padding: 4px 10px; border-radius: 20px; }
                .lg-trivia-score { background: #ffc300; color: #801039; padding: 4px 10px; border-radius: 20px; }
                .lg-trivia-question-box { width: 100%; background: #fff; border: 2px solid #801039; border-radius: 1rem; padding: 1rem; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
                .lg-trivia-question-text { font-size: 1.05rem; font-weight: bold; color: #333; margin: 0 0 0.8rem 0; line-height: 1.3; }
                .lg-trivia-options { display: grid; grid-template-columns: 1fr; gap: 0.4rem; }
                .lg-trivia-option { background: #fdf5f7; border: 2px solid transparent; border-radius: 0.6rem; padding: 0.5rem 0.8rem; font-size: 0.95rem; font-weight: 600; color: #555; cursor: pointer; transition: all 0.2s ease; text-align: left; }
                .lg-trivia-option:hover:not(:disabled) { background: #f8e8e8; border-color: #801039; }
                .lg-trivia-option.correct { background: #d4edda; border-color: #155724; color: #155724; font-weight: bold; }
                .lg-trivia-option.incorrect { background: #f8d7da; border-color: #721c24; color: #721c24; }
                .lg-trivia-option:disabled { cursor: not-allowed; }
                .lg-trivia-feedback { margin-top: 0.6rem; font-weight: bold; font-size: 0.95rem; min-height: 1.2rem; }
                .lg-trivia-actions { margin-top: 0.6rem; display: flex; justify-content: center; }
                .lg-trivia-final-screen { text-align: center; padding: 2rem 1rem; }
                .lg-trivia-final-screen h2 { font-family: 'Arial Black Web', sans-serif; font-size: 1.8rem; color: #801039; margin-top: 0; text-transform: uppercase; }
                .lg-trivia-final-screen p { font-size: 1.2rem; margin-bottom: 2rem; font-weight: bold; }
            `;
            document.head.appendChild(style);
        }

        const allQuestions = [
            {
                question: "¿Cuál es el plato más emblemático de la gastronomía tacneña?",
                options: ["Ceviche", "Lomo Saltado", "Picante a la Tacneña", "Arroz con Pollo"],
                answer: 2
            },
            {
                question: "El Arco Parabólico de Tacna fue construido en honor a los héroes de...",
                options: ["La Independencia del Perú", "La Guerra del Pacífico", "La Batalla de Ayacucho", "La Revolución de Túpac Amaru"],
                answer: 1
            },
            {
                question: "¿Qué significa 'participación ciudadana' en un gobierno regional?",
                options: ["Solo votar en elecciones", "Que los vecinos se involucren en decisiones y fiscalización", "Pagar impuestos puntualmente", "Asistir a desfiles"],
                answer: 1
            },
            {
                question: "¿Cuál es el principal objetivo de las 'obras por impuestos'?",
                options: ["Reducir los impuestos de las empresas", "Acelerar la construcción de infraestructura pública", "Generar empleos temporales", "Decorar la ciudad"],
                answer: 1
            },
            {
                question: "El símbolo de Fuerza Tacna es un oso andino. ¿Qué valor representa principalmente?",
                options: ["Agresividad y poder", "Pereza y descanso", "Fuerza, trabajo y protección", "Astucia y velocidad"],
                answer: 2
            },
            {
                question: "¿En qué año se reincorporó Tacna al Perú?",
                options: ["1879", "1921", "1929", "1900"],
                answer: 2
            },
            {
                question: "El complejo arqueológico de Miculla es famoso por sus...",
                options: ["Pirámides", "Petroglifos", "Acueductos", "Fortalezas"],
                answer: 1
            }
        ];

        let questions = [];
        let currentQuestionIndexInLevel = 0;
        let score = 0;
        let currentLevel = 1;
        
        const levelConfigs = [3, 4, 5, 5, 7];

        function startLevel(lvl) {
            currentLevel = lvl;
            currentQuestionIndexInLevel = 0;
            const qCount = levelConfigs[lvl - 1];
            // Barajar la base y elegir la cantidad específica
            questions = [...allQuestions].sort(() => 0.5 - Math.random()).slice(0, qCount);
            showQuestion();
        }

        function showQuestion() {
            const question = questions[currentQuestionIndexInLevel];
            container.innerHTML = `
                <div class="lg-game-wrapper lg-trivia-game">
                    <div style="width: 100%; text-align: center; color: #801039; font-weight: bold; margin-bottom: 0.5rem; font-size: 0.9rem;">Nivel ${currentLevel}/5</div>
                    <div class="lg-trivia-header">
                        <div class="lg-trivia-progress">Pregunta ${currentQuestionIndexInLevel + 1} / ${questions.length}</div>
                        <div class="lg-trivia-score">Puntaje: ${score}</div>
                    </div>
                    <div class="lg-trivia-question-box">
                        <p class="lg-trivia-question-text">${question.question}</p>
                        <div class="lg-trivia-options">
                            ${question.options.map((opt, i) => `<button class="lg-trivia-option" data-index="${i}">${opt}</button>`).join('')}
                        </div>
                        <div class="lg-trivia-feedback" id="lg-trivia-feedback"></div>
                        <div class="lg-trivia-actions">
                            <button class="lg-btn-primary" id="lg-trivia-next" style="display:none; padding: 0.6rem 2rem; font-size: 0.9rem;">Siguiente</button>
                        </div>
                    </div>
                    <div id="lg-trivia-win" class="lg-final-message" style="display:none;">
                        <h3 style="color:#801039; font-family: 'Arial Black Web', sans-serif; font-size: 1.8rem; text-transform: uppercase;">¡Nivel ${currentLevel} Superado! 🎉</h3>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <button class="lg-btn" id="lg-trivia-menu">Volver a juegos</button>
                            <button class="lg-btn-primary" id="lg-trivia-next-level">Siguiente Nivel</button>
                        </div>
                    </div>
                </div>
            `;

            container.querySelectorAll('.lg-trivia-option').forEach(btn => {
                btn.addEventListener('click', selectAnswer);
            });
            
            container.querySelector('#lg-trivia-menu').addEventListener('click', () => window.LuchitoGames.renderHome());
            container.querySelector('#lg-trivia-next-level').addEventListener('click', () => {
                startLevel(currentLevel + 1);
            });

            container.querySelector('#lg-trivia-next').addEventListener('click', () => {
                currentQuestionIndexInLevel++;
                if (currentQuestionIndexInLevel < questions.length) {
                    showQuestion();
                } else {
                    if (currentLevel < 5) {
                        container.querySelector('#lg-trivia-win').style.display = 'flex';
                    } else {
                        showFinalScore();
                    }
                }
            });
        }

        function selectAnswer(e) {
            const selectedButton = e.target;
            const selectedAnswerIndex = parseInt(selectedButton.dataset.index);
            const question = questions[currentQuestionIndexInLevel];
            const feedbackEl = container.querySelector('#lg-trivia-feedback');

            container.querySelectorAll('.lg-trivia-option').forEach(btn => {
                btn.disabled = true;
                if (parseInt(btn.dataset.index) === question.answer) {
                    btn.classList.add('correct');
                }
            });

            if (selectedAnswerIndex === question.answer) {
                score++;
                feedbackEl.textContent = "¡Correcto! 🎉";
                feedbackEl.style.color = "#155724";
            } else {
                selectedButton.classList.add('incorrect');
                feedbackEl.textContent = "¡Incorrecto! 🐻";
                feedbackEl.style.color = "#721c24";
            }

            container.querySelector('.lg-trivia-score').textContent = `Puntaje: ${score}`;
            container.querySelector('#lg-trivia-next').style.display = 'inline-block';
        }

        function showFinalScore() {
            // Cada respuesta correcta vale 100 puntos
            const finalScore = score * 100;
            // Llamar al sistema de ranking global
            window.LuchitoGames.showRankingPrompt(finalScore, 'trivia', container);
        }

        score = 0;
        startLevel(1);
    }
});