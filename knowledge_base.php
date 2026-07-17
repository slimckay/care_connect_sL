<?php
/**
 * Care Connect SL - Knowledge Base
 * Stores medical info, FAQs, and referral guidance
 * Version 3.0 - Improved search with scoring, more conditions, greeting detection
 */

class KnowledgeBase {
    private $data = [];
    
    public function __construct() {
        $this->loadData();
    }
    
    private function loadData() {
        $this->data = [
            // ============================================
            // GREETINGS & PERSONAL QUESTIONS
            // ============================================
            'greetings' => [
                'hello' => "👋 Hello! I'm CareConnect AI, your health assistant. How are you feeling today? I'm here to help with any health questions you have.",
                'hi' => "👋 Hi there! How are you doing today? I hope you're well. Can I help you with any health concerns?",
                'hey' => "👋 Hey! Nice to meet you. How's your day going? I'm here to answer your health questions.",
                'good morning' => "🌅 Good morning! How did you sleep? I'm here to help with any health questions today.",
                'good afternoon' => "☀️ Good afternoon! How are you feeling today? I hope you're having a great day.",
                'good evening' => "🌙 Good evening! How was your day? I'm here to help with any health concerns.",
                'how are you' => "😊 I'm doing well, thank you for asking! How about you? How are you feeling today?",
                'what\'s up' => "👋 Hey! Not much, just here to help with your health questions. How are you doing?",
                'how\'s it going' => "😊 Going great! How are you feeling today? I'm ready to help with any health issues.",
                'nice to meet you' => "😊 Nice to meet you too! I'm CareConnect AI, your health assistant. How can I help you today?"
            ],
            
            // ============================================
            // COMMON SICKNESS & SYMPTOMS (30+ Conditions)
            // ============================================
            'sickness' => [
                // ---------- RESPIRATORY ----------
                'cough' => [
                    'keywords' => ['cough', 'coughing', 'dry cough', 'wet cough', 'persistent cough', 'cough with phlegm'],
                    'response' => "🤧 **Cough Information:**

**Common Causes:**
• Viral infection (cold, flu)
• Allergies
• Asthma
• Smoking
• Acid reflux

✅ **Home Care:**
1️⃣ Drink warm fluids (honey lemon tea, ginger tea)
2️⃣ Use steam inhalation
3️⃣ Honey (1 tsp) for adults and children over 1 year
4️⃣ Rest and avoid irritants
5️⃣ Over-the-counter cough syrup (if available)

🚨 **See a doctor if:**
• Cough lasts more than 3 weeks
• Cough with blood
• Difficulty breathing
• Chest pain
• High fever
• Cough in children under 3 months

📍 **Care Connect can help you find a clinic or community health worker.**",
                    'referral_type' => 'moderate',
                    'urgency' => 'medium'
                ],
                
                'cold' => [
                    'keywords' => ['cold', 'runny nose', 'sneezing', 'sniffles', 'stuffy nose', 'congestion', 'common cold'],
                    'response' => "🤧 **Common Cold Symptoms:**
• Runny or stuffy nose
• Sneezing
• Sore throat
• Mild cough
• Fatigue
• Mild headache
• No fever or low-grade fever

✅ **Home Care:**
1️⃣ Rest and get plenty of sleep
2️⃣ Drink warm fluids (ginger tea, honey lemon)
3️⃣ Use steam inhalation for congestion
4️⃣ Salt water gargle for sore throat
5️⃣ Over-the-counter pain relievers (Paracetamol)
6️⃣ Honey (1 tsp) for cough (adults and children over 1 year)

📅 **Recovery:** Usually 7-10 days

🚨 **See a doctor if:**
• Symptoms last more than 10 days
• High fever develops
• Difficulty breathing
• Severe sinus pain
• Symptoms get worse instead of better

📍 **Care Connect can connect you with community health workers and pharmacies.**",
                    'referral_type' => 'routine',
                    'urgency' => 'low'
                ],
                
                'flu' => [
                    'keywords' => ['flu', 'influenza', 'high fever', 'body aches', 'chills', 'fatigue', 'dry cough', 'muscle pain'],
                    'response' => "🌡️ **Influenza (Flu) Symptoms:**
• High fever (38-40°C)
• Severe body aches and muscle pain
• Chills and sweating
• Dry cough
• Extreme fatigue (tiredness)
• Headache
• Sore throat

✅ **What to do:**
1️⃣ Rest in bed – your body needs energy to fight the virus
2️⃣ Drink plenty of fluids (water, ORS, warm soups)
3️⃣ Take Paracetamol for fever and pain
4️⃣ Use honey and warm drinks for cough
5️⃣ Stay home and avoid spreading to others
6️⃣ Eat nourishing foods when appetite returns

📅 **Recovery:** Usually 5-7 days, cough may last 2 weeks

🚨 **Emergency signs (seek care):**
• Difficulty breathing
• Chest pain
• Confusion
• Severe vomiting
• Fever lasting >5 days
• Worsening symptoms

📍 **Care Connect:** Find clinics near you for priority appointments.",
                    'referral_type' => 'moderate',
                    'urgency' => 'medium'
                ],
                
                'pneumonia' => [
                    'keywords' => ['pneumonia', 'chest pain', 'cough phlegm', 'breathing difficulty', 'high fever', 'shortness breath', 'rapid breathing'],
                    'response' => "🫁 **Pneumonia Symptoms:**
• Persistent cough with green/yellow phlegm
• High fever and chills
• Chest pain (worse when breathing/coughing)
• Shortness of breath
• Rapid breathing
• Extreme fatigue
• Confusion (in elderly)

⚠️ **THIS IS SERIOUS – SEEK CARE NOW!**

✅ **What to do:**
1️⃣ Seek medical attention immediately – pneumonia requires antibiotics
2️⃣ Go to nearest health center or hospital
3️⃣ Get a chest X-ray if available
4️⃣ Take antibiotics as prescribed (complete full course)
5️⃣ Rest and drink plenty of fluids
6️⃣ Use pain relievers for chest pain

🚨 **Emergency signs:**
• Blue lips or fingertips
• Difficulty breathing
• Confusion or drowsiness
• Unable to drink fluids
• Very high fever

📍 **Care Connect:** Fast-track to partner clinics, priority appointments, hospital referral if needed.",
                    'referral_type' => 'urgent',
                    'urgency' => 'high'
                ],
                
                // ---------- FEVER & INFECTIONS ----------
                'fever' => [
                    'keywords' => ['fever', 'high temperature', 'hot body', 'sweating', 'chills', 'feverish'],
                    'response' => "🌡️ **Fever Information:**

**Common Causes:**
• Malaria
• Flu or cold
• Infection (bacterial or viral)
• Typhoid
• Pneumonia

✅ **What to do:**
1️⃣ Rest and stay hydrated
2️⃣ Take Paracetamol (use correct dose for age/weight)
3️⃣ Use lukewarm sponging to cool down
4️⃣ Monitor temperature regularly
5️⃣ Get tested at nearest health center if fever persists

🚨 **Seek immediate care if:**
• Fever over 39°C (or 38°C in children)
• Fever lasting more than 3 days
• With severe headache, vomiting, or rash
• In children or elderly – seek help sooner
• Signs of dehydration

📍 **Care Connect can help you find testing and treatment near you.**",
                    'referral_type' => 'moderate',
                    'urgency' => 'medium'
                ],
                
                'malaria' => [
                    'keywords' => ['malaria', 'fever', 'chills', 'sweating', 'headache', 'vomiting', 'body ache'],
                    'response' => "🦟 **Malaria Symptoms:**
• High fever (39°C+) that comes and goes
• Chills and sweating
• Severe headache
• Nausea and vomiting
• Muscle and joint pain
• Fatigue and weakness

✅ **What to do:**
1️⃣ Get tested immediately at nearest health center (RDT or blood smear)
2️⃣ If positive, start Artemisinin-based Combination Therapy (ACT) within 24 hours
3️⃣ Rest and stay hydrated with ORS
4️⃣ Complete the full course of medication
5️⃣ Use mosquito nets to prevent spread

🚨 **Seek emergency care if:**
• Confusion or seizures
• Difficulty breathing
• Yellow skin/eyes (jaundice)
• Severe bleeding
• Unable to drink or keep food down

📍 **Care Connect can help you find testing centers, partner clinics, and community health workers.**",
                    'referral_type' => 'urgent',
                    'urgency' => 'high'
                ],
                
                'typhoid' => [
                    'keywords' => ['typhoid', 'fever', 'stomach pain', 'constipation', 'diarrhea', 'rose spots', 'abdominal'],
                    'response' => "🌡️ **Typhoid Symptoms:**
• Sustained high fever (39-40°C)
• Severe stomach pain and discomfort
• Constipation or diarrhea
• Headache and weakness
• Rose-colored spots on chest/abdomen
• Loss of appetite

✅ **What to do:**
1️⃣ Seek medical attention immediately – typhoid requires antibiotics
2️⃣ Get a blood test (Widal test or blood culture)
3️⃣ Start antibiotics as prescribed
4️⃣ Rest and drink plenty of clean water
5️⃣ Good hygiene to prevent spreading

🚨 **Hospital needed if:**
• Severe abdominal pain
• Vomiting and unable to keep food down
• Dehydration signs
• Confusion or delirium

📍 **Care Connect helps find nearby clinics with labs and specialist care.**",
                    'referral_type' => 'urgent',
                    'urgency' => 'high'
                ],
                
                // ---------- HEAD & NEUROLOGICAL ----------
                'headache' => [
                    'keywords' => ['headache', 'head pain', 'migraine', 'tension headache', 'pressure head'],
                    'response' => "😫 **Headache – Types & Relief:**

**Common Types:**
• Tension headache (mild to moderate, both sides)
• Migraine (throbbing, one side, with nausea)
• Sinus headache (around eyes, with congestion)
• Dehydration headache
• Fever-related headache

✅ **Home Relief:**
1️⃣ Rest in a quiet, dark room
2️⃣ Drink plenty of water (dehydration causes headaches!)
3️⃣ Apply cold or warm compress to forehead
4️⃣ Take Paracetamol or Ibuprofen (if available)
5️⃣ Gentle neck massage
6️⃣ Avoid bright lights and loud noise
7️⃣ Get fresh air

🚨 **Seek urgent care if:**
• Severe, sudden headache (worst ever)
• Headache with:
  - Fever
  - Stiff neck
  - Confusion
  - Vision changes
  - Numbness
  - Seizure
  - Head injury
• Headache lasts more than 3 days

📍 **Care Connect:** Help determine if it's serious, connect to appropriate care.",
                    'referral_type' => 'routine',
                    'urgency' => 'low'
                ],
                
                'migraine' => [
                    'keywords' => ['migraine', 'severe headache', 'throbbing head', 'nausea headache', 'light sensitivity'],
                    'response' => "⚡ **Migraine Symptoms:**
• Severe, throbbing pain (usually one side)
• Nausea and vomiting
• Sensitivity to light and sound
• Vision changes (auras)
• Lasts 4-72 hours

✅ **What to do:**
1️⃣ Rest in a dark, quiet room
2️⃣ Apply cold compress to forehead
3️⃣ Drink water (dehydration can trigger)
4️⃣ Take pain relievers (Paracetamol/Ibuprofen)
5️⃣ Avoid triggers: stress, certain foods, lack of sleep
6️⃣ Sleep if possible

🆘 **Seek care if:**
• First severe headache
• Worst headache ever
• Headache with fever
• Headache after head injury
• Taking painkillers frequently

📍 **Care Connect:** Find specialist care if needed, long-term management support.",
                    'referral_type' => 'routine',
                    'urgency' => 'low'
                ],
                
                // ---------- STOMACH & DIGESTIVE ----------
                'diarrhea' => [
                    'keywords' => ['diarrhea', 'running stomach', 'loose stool', 'stomach cramp', 'dehydration', 'watery stool'],
                    'response' => "💧 **Diarrhea (Running Stomach) Management:**

**Home Care:**
1️⃣ Drink plenty of clean water + ORS (Oral Rehydration Solution)
   • Recipe: 1 liter clean water + 6 tsp sugar + 1/2 tsp salt
2️⃣ Continue eating small meals (rice, bananas, toast, porridge)
3️⃣ Avoid sugary drinks, dairy, and greasy food
4️⃣ Zinc supplements (10-20mg daily for children)
5️⃣ Rest and monitor symptoms

✅ **Good foods to eat:**
• Rice porridge
• Boiled bananas
• Toast/bread
• Chicken soup
• Carrots

🚨 **See a doctor if:**
• Diarrhea lasts more than 3 days
• Blood in stool
• Severe dehydration signs
• High fever
• For children and elderly – seek help sooner

📍 **Care Connect can connect you with community health workers and clinics.**",
                    'referral_type' => 'moderate',
                    'urgency' => 'medium'
                ],
                
                'vomiting' => [
                    'keywords' => ['vomiting', 'throwing up', 'nausea', 'sick stomach', 'regurgitation'],
                    'response' => "🤢 **Vomiting & Nausea Management:**

**Home Care:**
1️⃣ Stop eating solid food for 6-8 hours
2️⃣ SIP small amounts of water or ORS frequently
3️⃣ After vomiting stops, try clear liquids then bland foods
4️⃣ Rest and avoid strong smells
5️⃣ Ginger tea can help nausea

🚨 **Seek medical help if:**
• Vomiting lasts more than 24 hours
• Unable to keep down any fluids
• Vomiting blood or dark material
• Severe abdominal pain
• Signs of dehydration
• Head injury or severe headache
• For children – seek help sooner

📋 **Remember:** Sip, don't gulp. Small amounts frequently.

📍 **Care Connect can help find nearby clinics and arrange home visits.**",
                    'referral_type' => 'moderate',
                    'urgency' => 'medium'
                ],
                
                'stomach_pain' => [
                    'keywords' => ['stomach pain', 'abdominal pain', 'belly ache', 'tummy pain', 'cramps'],
                    'response' => "🤰 **Stomach Pain – Causes & Action:**

**Common Causes in Sierra Leone:**
• Food poisoning
• Indigestion
• Constipation
• Diarrhea
• Typhoid
• Malaria
• Stomach ulcers
• Appendicitis (severe)

✅ **Home Care (mild pain):**
1️⃣ Rest in a comfortable position
2️⃣ Drink warm water or herbal tea
3️⃣ Avoid heavy or spicy food
4️⃣ Use a hot water bottle for comfort
5️⃣ Eat small, bland meals

🚨 **Seek urgent care if:**
• Severe, sudden pain
• Pain with vomiting
• Fever with abdominal pain
• Blood in stool or vomit
• Pain after injury
• Pain in right lower side (possible appendicitis)
• Unable to eat or drink
• Pain with pregnancy

📍 **Care Connect:** Help identify the cause, connect to appropriate providers.",
                    'referral_type' => 'moderate',
                    'urgency' => 'medium'
                ],
                
                // ---------- SKIN ----------
                'skin_rash' => [
                    'keywords' => ['rash', 'skin rash', 'itching', 'hives', 'spots', 'red skin', 'skin irritation'],
                    'response' => "🔴 **Skin Rash – Causes & Care:**

**Common Causes:**
• Heat rash
• Allergic reaction
• Fungal infection (ringworm)
• Scabies (intense itching)
• Measles (with fever)
• Chickenpox
• Eczema (dry, itchy skin)
• Contact dermatitis

✅ **Home Care:**
1️⃣ Keep area clean and dry
2️⃣ Use mild soap and cool water
3️⃣ Apply calamine lotion for itching
4️⃣ Avoid scratching (to prevent infection)
5️⃣ Wear loose, cotton clothing
6️⃣ Cold compresses for relief

🚨 **Seek care if:**
• Rash with high fever
• Rash spreading rapidly
• Blisters or open sores
• Painful rash
• Rash near eyes or mouth
• Signs of infection
• Difficulty breathing (allergic reaction)

📍 **Care Connect:** Find skin clinic, referral to dermatologist, community health worker visit.",
                    'referral_type' => 'routine',
                    'urgency' => 'low'
                ],
                
                // ---------- MUSCULOSKELETAL ----------
                'back_pain' => [
                    'keywords' => ['back pain', 'lower back', 'spine', 'backache', 'sciatica', 'lumbar'],
                    'response' => "💪 **Back Pain – Management:**

**Causes:**
• Heavy lifting
• Poor posture
• Sitting for long periods
• Injury or strain
• Arthritis
• Pregnancy

✅ **Home Care:**
1️⃣ Rest (but stay active – bed rest only 1-2 days)
2️⃣ Apply heat pack or cold compress
3️⃣ Gentle stretching exercises
4️⃣ Good posture while sitting/standing
5️⃣ Use firm mattress or floor mat
6️⃣ Pain relievers (Paracetamol/Ibuprofen)

📋 **Exercises (start gently):**
• Cat-cow stretch
• Child's pose
• Pelvic tilts
• Wall sits

🚨 **Seek urgent care if:**
• Pain after injury/fall
• Numbness in legs
• Loss of bladder/bowel control
• Pain with fever
• Unexplained weight loss
• Pain lasting more than 2 weeks

📍 **Care Connect:** Find physical therapy, connect with orthopedic care.",
                    'referral_type' => 'routine',
                    'urgency' => 'low'
                ],
                
                'joint_pain' => [
                    'keywords' => ['joint pain', 'arthritis', 'swollen joint', 'knee pain', 'shoulder pain'],
                    'response' => "🦴 **Joint Pain – Causes & Relief:**

**Causes:**
• Osteoarthritis (wear and tear)
• Rheumatoid arthritis (inflammation)
• Injury
• Gout (sudden severe pain)
• Infection (needs urgent care)

✅ **Home Care:**
1️⃣ Rest the affected joint
2️⃣ Apply ice pack for swelling
3️⃣ Compression bandage (not too tight)
4️⃣ Elevate if possible
5️⃣ Gentle exercises (range of motion)
6️⃣ Paracetamol or Ibuprofen

📋 **Long-term:**
• Maintain healthy weight
• Regular gentle exercise (walking, swimming)
• Good nutrition (calcium, vitamin D)

🚨 **Seek care if:**
• Sudden, severe pain
• Joint is red, hot, swollen (infection)
• Unable to move joint
• Pain with fever
• Joint pain after injury
• Symptoms last more than 2 weeks

📍 **Care Connect:** Connect to physiotherapy, rheumatology referral.",
                    'referral_type' => 'routine',
                    'urgency' => 'low'
                ],
                
                // ---------- EYES ----------
                'eye_infection' => [
                    'keywords' => ['eye infection', 'red eye', 'conjunctivitis', 'pink eye', 'eye discharge', 'sore eye'],
                    'response' => "👁️ **Eye Infection (Conjunctivitis):**

**Symptoms:**
• Redness in the eye
• Watery or thick discharge
• Itchy or gritty feeling
• Swollen eyelids
• Crusty eyelids in the morning
• Sensitivity to light

✅ **What to do:**
1️⃣ Wash hands frequently
2️⃣ Use clean cloth/tissue to wipe discharge
3️⃣ Apply warm compresses
4️⃣ Avoid touching/rubbing eyes
5️⃣ Don't share towels, pillows, or eye drops
6️⃣ Stay home from school/work until resolved

🚨 **Seek care if:**
• Severe pain
• Vision changes/blurring
• Light sensitivity
• Green/yellow thick discharge
• Eye injury
• Symptoms last more than 3 days
• Recurrent infections

⚠️ **Very contagious!** – prevent spreading

📍 **Care Connect:** Find eye clinic, connect to ophthalmologist.",
                    'referral_type' => 'moderate',
                    'urgency' => 'medium'
                ],
                
                // ---------- MOUTH & THROAT ----------
                'sore_throat' => [
                    'keywords' => ['sore throat', 'pain throat', 'tonsillitis', 'difficulty swallowing', 'throat infection'],
                    'response' => "🗣️ **Sore Throat – Relief & Care:**

**Causes:**
• Viral infection (most common)
• Bacterial infection (strep throat)
• Allergies
• Dry air
• Acid reflux

✅ **Home Care:**
1️⃣ Gargle with warm salt water (1/2 tsp salt in warm water)
2️⃣ Drink warm fluids (honey lemon tea, ginger tea)
3️⃣ Use throat lozenges or honey
4️⃣ Rest your voice
5️⃣ Avoid spicy or acidic foods
6️⃣ Use a humidifier or steam inhalation
7️⃣ Pain relievers (Paracetamol)

🚨 **Seek care if:**
• Severe pain
• Difficulty breathing or swallowing
• Fever >38.5°C
• Swollen glands
• White patches on tonsils
• Rash
• Symptoms last more than 3 days

📍 **Care Connect:** Find pharmacy or clinic, connect to ENT specialist if needed.",
                    'referral_type' => 'routine',
                    'urgency' => 'low'
                ],
                
                // ---------- DENTAL ----------
                'toothache' => [
                    'keywords' => ['toothache', 'tooth pain', 'dental', 'gum', 'cavity', 'infection tooth'],
                    'response' => "🦷 **Toothache – Immediate Relief:**

**Causes:**
• Cavities
• Gum infection
• Abscess
• Cracked tooth
• Wisdom tooth

✅ **Immediate Relief:**
1️⃣ Rinse with warm salt water
2️⃣ Apply cold compress outside cheek
3️⃣ Clove oil on affected area (if available)
4️⃣ Use dental floss to remove food stuck
5️⃣ Pain relievers (Paracetamol)
6️⃣ Sleep with head elevated

🚨 **Seek urgent care if:**
• Severe, persistent pain
• Swelling in face or jaw
• Fever
• Difficulty swallowing or breathing
• Bleeding that doesn't stop
• Signs of infection (pus, bad taste)

📍 **Care Connect:** Find dental clinic, emergency dental care referral.",
                    'referral_type' => 'moderate',
                    'urgency' => 'medium'
                ],
                
                // ---------- URINARY ----------
                'uti' => [
                    'keywords' => ['uti', 'urinary infection', 'urine pain', 'burning urine', 'frequent urine'],
                    'response' => "🚽 **Urinary Tract Infection (UTI):**

**Symptoms:**
• Burning pain when urinating
• Frequent urge to urinate (but little comes out)
• Lower abdominal pain
• Cloudy or strong-smelling urine
• Blood in urine
• Back/side pain (kidney infection)

✅ **What to do:**
1️⃣ Drink plenty of water
2️⃣ Urinate frequently (don't hold)
3️⃣ Use unscented soap
4️⃣ Wear cotton underwear
5️⃣ Avoid caffeine and alcohol

🚨 **Seek care if:**
• Fever with UTI symptoms
• Back pain (kidney involvement)
• Blood in urine
• Symptoms not improving after 1-2 days
• Pregnant women – seek care immediately
• Elderly – seek care soon

⚠️ **UTI needs antibiotics** – don't ignore!

📍 **Care Connect:** Find clinic near you, urgent care referral, follow-up support.",
                    'referral_type' => 'moderate',
                    'urgency' => 'medium'
                ],
                
                // ---------- MALNUTRITION ----------
                'malnutrition' => [
                    'keywords' => ['malnutrition', 'thin child', 'kwashiorkor', 'marasmus', 'undernourished', 'weight loss'],
                    'response' => "🍚 **Malnutrition – Signs & Care:**

**Signs in Children:**
• Underweight for age
• Swelling (kwashiorkor) – feet, hands, face
• Very thin with no fat (marasmus)
• Fatigue and lack of energy
• Slow growth
• Frequent infections

**Causes:**
• Lack of nutritious food
• Repeated infections (diarrhea, malaria)
• Poor breastfeeding practices
• Poverty and food insecurity

✅ **What to do:**
1️⃣ Visit health center for assessment
2️⃣ Get nutrition supplements (Plumpy'Nut, ready-to-use foods)
3️⃣ Ensure balanced diet (protein, carbohydrates, vegetables)
4️⃣ Exclusive breastfeeding for 6 months
5️⃣ Treat underlying infections
6️⃣ Regular monitoring of growth

📋 **Good Foods:**
• Rice and beans
• Eggs, fish, chicken
• Groundnuts (peanuts)
• Fruits (mango, banana, orange)
• Vegetables (green leafy)
• Fortified porridges

📍 **Care Connect:** Nutrition program enrollment, monthly check-ups, home visits.",
                    'referral_type' => 'urgent',
                    'urgency' => 'high'
                ],
                
                // ---------- MENTAL HEALTH ----------
                'anxiety' => [
                    'keywords' => ['anxiety', 'worry', 'stress', 'nervous', 'panic', 'overwhelmed', 'fear'],
                    'response' => "🧠 **Anxiety – Coping Strategies:**

**Symptoms:**
• Excessive worry
• Racing thoughts
• Difficulty sleeping
• Restlessness
• Heart palpitations
• Sweating
• Shallow breathing

✅ **Coping Strategies:**
1️⃣ **Deep breathing:** Breathe in 4 sec, hold 4 sec, out 4 sec
2️⃣ **Grounding technique:** 5-4-3-2-1 method
   - 5 things you can see
   - 4 things you can touch
   - 3 things you can hear
   - 2 things you can smell
   - 1 thing you can taste
3️⃣ **Talk to someone you trust**
4️⃣ **Exercise** (walking, stretching)
5️⃣ **Reduce caffeine and sugar**
6️⃣ **Get enough sleep**
7️⃣ **Write down worries** (journaling)

📋 **When to seek help:**
• Anxiety affecting daily life
• Panic attacks
• Thoughts of self-harm
• Not sleeping for days
• Can't work or socialize

📍 **Care Connect:** Connect to mental health counselors, referral to specialists.",
                    'referral_type' => 'moderate',
                    'urgency' => 'medium'
                ],
                
                'depression' => [
                    'keywords' => ['depression', 'sadness', 'hopeless', 'tired all the time', 'losing interest', 'suicidal'],
                    'response' => "💔 **Depression – Signs & Support:**

**Symptoms (lasting >2 weeks):**
• Persistent sadness or low mood
• Loss of interest in activities
• Changes in appetite
• Sleep problems
• Fatigue and low energy
• Feeling worthless or guilty
• Difficulty concentrating
• Thoughts of death or suicide

✅ **What to do:**
1️⃣ **Talk to someone** – family, friend, counselor
2️⃣ **Seek professional help** – mental health is treatable
3️⃣ **Get moving** – gentle exercise helps
4️⃣ **Connect with others** – isolation makes it worse
5️⃣ **Set small goals** – don't expect too much
6️⃣ **Eat regular meals** – even if not hungry

🚨 **URGENT – Get immediate help if:**
• Thoughts of suicide or self-harm
• Making plans to hurt yourself
• Hearing voices
• Unable to care for yourself

📞 **Emergency support:**
• Call a trusted person NOW
• Go to nearest health center
• Call Care Connect help line

📍 **Care Connect:** Confidential mental health support, counselor referral, regular check-ins.",
                    'referral_type' => 'urgent',
                    'urgency' => 'high'
                ],
                
                // ---------- PREGNANCY & MATERNAL ----------
                'pregnancy' => [
                    'keywords' => ['pregnancy', 'pregnant', 'antenatal', 'labor', 'delivery', 'maternal', 'expecting'],
                    'response' => "🤰 **Pregnancy Care Guide:**

**Antenatal Care (ANC):**
• Start ANC in first trimester (as soon as you know)
• Monthly check-ups (then weekly in 3rd trimester)
• Take folic acid (first 3 months) – prevents birth defects
• Iron supplements to prevent anemia
• Tetanus vaccination (2 doses)
• Regular blood pressure and urine checks
• HIV and syphilis testing (offered at clinics)

✅ **Healthy Pregnancy:**
• Eat nutritious meals (iron-rich foods: greens, beans, meat)
• Drink plenty of clean water
• Rest adequately
• Avoid harmful substances (alcohol, smoking)
• Attend all ANC appointments
• Plan for delivery location

🚨 **Emergency signs (call 999):**
• Severe abdominal pain
• Heavy bleeding (in pregnancy or after)
• Severe headache
• Blurred vision
• Reduced baby movement
• Fever or chills
• Water breaking before 37 weeks
• High blood pressure
• Convulsions

📍 **Care Connect can:** Find nearest ANC clinics, referral to maternity centers, connect with midwives, home visit programs.",
                    'referral_type' => 'urgent',
                    'urgency' => 'high'
                ],
                
                'childbirth' => [
                    'keywords' => ['labor', 'childbirth', 'delivery', 'giving birth', 'contractions', 'baby coming'],
                    'response' => "👶 **Signs You Are in Labor:**
• Regular contractions (every 5-10 minutes)
• Back pain that comes and goes
• Water breaking (fluid leaking)
• Bloody show (mucus with blood)

✅ **What to do:**
1️⃣ **GO TO HEALTH FACILITY** – do not deliver at home
2️⃣ Call transport or ambulance
3️⃣ Bring: ANC card, clean cloth, baby clothes, supplies
4️⃣ Don't eat heavy food – drink water
5️⃣ Time contractions
6️⃣ Stay calm and breathe

🚨 **Call 999 IMMEDIATELY if:**
• Heavy bleeding
• Baby is not moving
• Seizures
• Severe headache
• Labor before 37 weeks (premature)

📍 **Care Connect:** Hospital referral, emergency transport coordination, birth companion support, postnatal follow-up.",
                    'referral_type' => 'urgent',
                    'urgency' => 'high'
                ],
                
                // ---------- CHILD HEALTH ----------
                'childhood_illness' => [
                    'keywords' => ['child', 'baby', 'infant', 'toddler', 'fever child', 'sick child', 'measles'],
                    'response' => "👶 **Childhood Illness Guide:**

**Common Childhood Issues:**

🦟 **Malaria in Children:**
• High fever (38°C+) 
• Lethargy and poor feeding
• Vomiting
• Convulsions (seizures)
• Get tested at health center immediately

🌡️ **Fever Management:**
• Give Paracetamol (use correct dose for age/weight)
• Remove excess clothing
• Sponge with lukewarm water (not cold)
• Monitor temperature
• Seek care if fever persists >24h

💧 **Diarrhea/Dehydration:**
• Give ORS immediately
• Continue breastfeeding or feeding
• Small, frequent feedings
• Watch for: sunken eyes, dry mouth, no urine

🚨 **Emergency signs (seek care now):**
• Difficulty breathing
• Unconsciousness
• Convulsions
• Unable to drink or breastfeed
• Severe vomiting
• Very high fever
• Severe dehydration
• Jaundice (yellow skin/eyes)

📋 **Prevention:**
• Complete vaccination schedule (BCG, polio, measles, etc.)
• Good nutrition
• Proper hygiene and handwashing
• Use mosquito nets
• Regular growth monitoring

📍 **Care Connect SL:** Find pediatric care, referral to child health specialists, vaccination program access.",
                    'referral_type' => 'urgent',
                    'urgency' => 'high'
                ],
                
                // ---------- INJURIES ----------
                'injury' => [
                    'keywords' => ['injury', 'wound', 'cut', 'bleeding', 'sprain', 'fracture', 'fall', 'accident'],
                    'response' => "🩹 **Injury – First Aid:**

**For Cuts and Wounds:**
1️⃣ Apply pressure with clean cloth to stop bleeding
2️⃣ Wash with clean water and soap
3️⃣ Apply antiseptic (iodine or alcohol)
4️⃣ Cover with clean bandage
5️⃣ Get tetanus shot if not vaccinated

**For Sprains (swollen ankle/wrist):**
1️⃣ **R**est – don't use the injured part
2️⃣ **I**ce – apply cold pack (not directly on skin)
3️⃣ **C**ompression – wrap with bandage
4️⃣ **E**levate – raise above heart level

**For Fractures (broken bone):**
• DO NOT move the person
• Immobilize the injured area
• Use splint (stick/board) if possible
• Apply ice pack around it
• Get to hospital immediately

🚨 **Call 999 if:**
• Severe bleeding (not stopping)
• Deep wound
• Head injury
• Unconsciousness
• Difficulty breathing
• Broken bone protruding
• Poisoning
• Burn (large area)

📍 **Care Connect:** Hospital referral, emergency coordination, follow-up care.",
                    'referral_type' => 'urgent',
                    'urgency' => 'high'
                ],
                
                // ---------- BURNS ----------
                'burn' => [
                    'keywords' => ['burn', 'scald', 'fire burn', 'hot water', 'cooking burn', 'sunburn'],
                    'response' => "🔥 **Burn – First Aid:**

**Immediate Steps:**
1️⃣ **Cool the burn** – run cool (not cold) water over it for 10-15 minutes
2️⃣ Remove jewelry and tight clothing from burned area
3️⃣ Cover with clean cloth or cling film
4️⃣ Take Paracetamol for pain
5️⃣ Drink water to prevent dehydration

❌ **DO NOT:**
• Use ice directly on burn
• Pop blisters
• Apply butter, oil, or toothpaste
• Remove stuck clothing
• Use cotton (fibers may stick)

🚨 **Seek emergency care if:**
• Large burn (bigger than hand size)
• Deep burn (charred or white)
• Burn on face, hands, feet, or genitals
• Burn caused by chemicals or electricity
• Burn with difficulty breathing
• Burn with other injuries
• Child or elderly with burn

📍 **Care Connect:** Hospital referral, specialist burn care, follow-up and physiotherapy.",
                    'referral_type' => 'urgent',
                    'urgency' => 'high'
                ]
            ],
            
            // ============================================
            // FAQs
            // ============================================
            'faq' => [
                'what is care connect' => "🏥 **Care Connect SL** is Sierra Leone's home-based medical referral platform. We connect patients, caregivers, and healthcare providers across 15+ districts with 180+ community health workers and 65+ partner clinics.\n\n**Our Mission:** To bridge the gap between communities and healthcare providers, making referrals faster, easier, and more efficient.",
                
                'how to use' => "📋 **How to Use Care Connect:**\n\n1️⃣ **Sign Up** – Create an account (Patient/Caregiver/Provider)\n2️⃣ **Submit Referral** – Go to 'Referrals' page, fill in patient details\n3️⃣ **Track** – Check status on your dashboard\n4️⃣ **Follow Up** – Communicate with providers\n\n💡 Quick tips: Visit 'Find Care' to discover providers, or contact help@careconnect.sl for support.",
                
                'cost' => "💰 **Care Connect SL is FREE to use!** ✅\n\n**Free:** Account creation, referrals, finding providers, health information\n**Costs:** Medical services, medications, tests at clinics/hospitals\n\n💡 Some partner clinics offer free consultations – check their profiles!",
                
                'privacy' => "🔒 **Your privacy matters:**\n• All data is encrypted\n• Medical information is confidential\n• Only authorized providers see your health data\n• You can access, correct, or delete your data anytime\n\nWe never sell your data to third parties.",
                
                'emergency' => "🚨 **EMERGENCY PROCEDURES:**\n\n📞 **CALL 999 IMMEDIATELY** 📞\n\n**Emergencies:** Heart attack, stroke, severe bleeding, difficulty breathing, unconsciousness, seizures, poisoning, severe head injury.\n\n**What to do:**\n1. Call 999 for ambulance\n2. Go to nearest emergency room\n3. Keep patient comfortable\n4. Apply pressure to bleeding\n5. Stay calm and reassure patient\n\nAfter emergency, contact Care Connect for follow-up care.",
                
                'providers' => "🏥 **Our Healthcare Provider Network:**\n• Doctors (General practitioners, specialists)\n• Clinics and Hospitals (Govt & Private)\n• Pharmacies, Labs, Mental Health counselors\n• Midwives, Nurses, Community Health Workers\n• Quality standards: All licensed, regular checks\n\nFind a Provider: Use 'Find Care' page to search by location, specialty, or service.",
                
                'referral_process' => "📋 **The Referral Process:**\n\n1️⃣ **Submit** – Fill patient details, symptoms, choose provider (optional)\n2️⃣ **Review** – Care Connect reviews and triages urgency\n3️⃣ **Match** – Connected with appropriate provider\n4️⃣ **Care** – Provider contacts patient, schedules appointment\n5️⃣ **Follow-up** – Rate experience, Care Connect follows up\n\n**Timeframes:** Urgent: 4-6 hours | Moderate: 24 hours | Routine: 48-72 hours",
                
                'symptoms' => "🤒 **Not sure about your symptoms?**\n\nHere's what to do:\n1. Describe your symptoms to me (fever, pain, rash, etc.)\n2. I'll help identify possible causes\n3. I'll recommend if you should see a doctor\n4. I can help you find the right care\n\n**Remember:** I provide information only. Always consult a healthcare professional for proper diagnosis."
            ],
            
            // ============================================
            // REFERRAL ROUTING
            // ============================================
            'referral_routing' => [
                'urgent' => [
                    'message' => "⚠️ **URGENT REFERRAL NEEDED**\n\nBased on the symptoms described, immediate medical attention is recommended.\n\n**Action Steps:**\n1. **Call 999** if life-threatening emergency\n2. **Go to nearest hospital emergency room** \n3. **Call Care Connect hotline:** +232 76 000 000\n4. **We'll coordinate with the hospital**\n\n🚨 **Do not wait!** Your health is our priority.",
                    'target' => 'emergency'
                ],
                'high' => [
                    'message' => "⚠️ **URGENT – SEEK CARE IMMEDIATELY**\n\nPlease go to the nearest health facility or hospital NOW.\n\nCare Connect will:\n• Alert the facility of your arrival\n• Prepare referral documents\n• Follow up on your care\n\n🚨 **Do not delay!** This condition requires prompt medical attention.",
                    'target' => 'emergency'
                ],
                'moderate' => [
                    'message' => "🏥 **REFERRAL RECOMMENDED**\n\nYou should see a healthcare provider within 24-48 hours.\n\n**Next Steps:**\n1. Visit 'Find Care' page on Care Connect\n2. Search for clinics near you\n3. Or submit a referral through the platform\n\n📋 We'll help with appointment scheduling, transport coordination, and follow-up.",
                    'target' => 'clinic'
                ],
                'medium' => [
                    'message' => "🏥 **REFERRAL RECOMMENDED**\n\nYou should see a healthcare provider within 24-48 hours.\n\n**Next Steps:**\n1. Use 'Find Care' to find a clinic near you\n2. Submit a referral through the platform\n3. A Care Connect team member will help\n\n📍 We're here to help – don't delay care.",
                    'target' => 'clinic'
                ],
                'routine' => [
                    'message' => "📋 **ROUTINE REFERRAL**\n\nThis appears to be a routine healthcare need that can be scheduled conveniently.\n\n**Options:**\n1. Book an appointment through Care Connect\n2. Visit a community health center\n3. Schedule with a partner clinic\n\n💡 Pro Tip: Use our 'Find Care' page to compare providers.\n\nTypical timeline: 48-72 hours.",
                    'target' => 'routine'
                ],
                'low' => [
                    'message' => "📋 **ROUTINE REFERRAL**\n\nThis appears to be a routine healthcare need.\n\n**Options:**\n1. Book an appointment through Care Connect\n2. Visit a community health center\n3. Schedule with a partner clinic\n\n💡 Check our 'Find Care' page for nearby providers.",
                    'target' => 'routine'
                ]
            ]
        ];
    }
    
    // ============================================
    // IMPROVED SEARCH WITH SCORING
    // ============================================
    public function search($query) {
        $query_lower = strtolower(trim($query));
        $results = [];
        
        // 1. Check for greetings first
        foreach ($this->data['greetings'] as $key => $response) {
            if (strpos($query_lower, $key) !== false) {
                $results['greeting'] = $response;
                return $results; // Return immediately
            }
        }
        
        // 2. Search sickness database with scoring
        $bestMatch = null;
        $bestScore = 0;
        $bestKeywords = [];
        
        foreach ($this->data['sickness'] as $key => $info) {
            $score = 0;
            $matchedKeywords = [];
            foreach ($info['keywords'] as $keyword) {
                if (strpos($query_lower, $keyword) !== false) {
                    $score += 3; // Exact match
                    $matchedKeywords[] = $keyword;
                } elseif (strpos($keyword, $query_lower) !== false) {
                    $score += 1; // Partial match (keyword contains query)
                } elseif (strpos($query_lower, $keyword) !== false) {
                    $score += 1; // Partial match (query contains keyword)
                }
            }
            // Bonus for multiple matches
            if ($score > 0) {
                $score += count($matchedKeywords) * 0.5;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $key;
                }
            }
        }
        
        if ($bestMatch && $bestScore > 0) {
            $results['sickness'] = $this->data['sickness'][$bestMatch];
            $results['sickness_key'] = $bestMatch;
        }
        
        // 3. Search FAQ
        foreach ($this->data['faq'] as $key => $faq) {
            if (strpos($query_lower, $key) !== false) {
                $results['faq'] = $faq;
                $results['faq_key'] = $key;
                break;
            }
        }
        
        // 4. Search for referral routing if sickness found
        if (isset($results['sickness'])) {
            $urgency = $results['sickness']['urgency'];
            if (isset($this->data['referral_routing'][$urgency])) {
                $results['referral'] = $this->data['referral_routing'][$urgency];
            }
        }
        
        return $results;
    }
    
    // ============================================
    // GET FULL RESPONSE
    // ============================================
    public function getResponse($query) {
        $result = $this->search($query);
        
        if (empty($result)) {
            return null; // No match found
        }
        
        // If greeting, return it directly
        if (isset($result['greeting'])) {
            return $result['greeting'];
        }
        
        $response = [];
        
        // Add sickness info if found
        if (isset($result['sickness'])) {
            $response[] = $result['sickness']['response'];
        }
        
        // Add referral routing
        if (isset($result['referral'])) {
            $response[] = "\n\n---\n" . $result['referral']['message'];
        }
        
        // Add FAQ if found and not already covered
        if (isset($result['faq']) && !isset($result['sickness'])) {
            $response[] = $result['faq'];
        }
        
        return implode("\n\n", $response);
    }
    
    // ============================================
    // GET ALL SICKNESSES (for reference)
    // ============================================
    public function getAllSicknesses() {
        return array_keys($this->data['sickness']);
    }
    
    // ============================================
    // GET ALL FAQS (for reference)
    // ============================================
    public function getAllFaqs() {
        return array_keys($this->data['faq']);
    }
}
?>