import os
import streamlit as st
from datetime import date, datetime
import base64
from pathlib import Path
from gc_service import GoogleCalendar

# 🧾 CONFIGURACIÓN PRINCIPAL DE LA APP
st.set_page_config(page_title="Wabi Sabi Booking", layout="centered")

# 🎨 CARGAR ESTILOS CSS
with open("styles/wizard-visual.css") as f:
    st.markdown(f"<style>{f.read()}</style>", unsafe_allow_html=True)

# 💈 DATOS BASE: SERVICIOS Y SEDES
SERVICIOS = [
    ("Corte de Cabello Clásico",
     "Incluye diagnóstico, corte a máquina con disminución gradual en laterales, perfilado de nuca y patillas, y estilizado final.",
     7, 40),
    ("Corte Tendencia (Fade o Degradado)",
     "El look del momento. Incluye degradado alto, medio o bajo, o Taper Fade con máquina y navaja.",
     8, 45),
    ("Perfilado de Cejas",
     "Perfilado con navaja o pinza, recorte profesional y aplicación de gel o tónico calmante.",
     3, 15),
    ("Diseño y Perfilado de Cejas con CERA",
     "Eliminación de vello no deseado y acabado limpio y natural.",
     5, 20),
    ("Arreglo y Perfilado de Barba",
     "Recorte, delineado y humectación con aceite o bálsamo.",
     5, 30),
    ("Corte de Cabello y Barba",
     "Corte completo + arreglo de barba con toalla caliente.",
     12, 60),
    ("Barba SPA (Ritual Tradicional)",
     "Diseño, delineado, vapor de ozono y bálsamo hidratante.",
     8, 45),
    ("Asesoría de Imagen y Estilismo Personal",
     "Análisis de rostro, tipo de cabello y recomendaciones personalizadas.",
     15, 90),
    ("Servicio VIP Completo",
     "Corte, lavado, barba y mascarilla facial express.",
     20, 120),
    ("Ritual VIP Exclusivo",
     "Corte + barba + cejas, productos premium y bebida de cortesía.",
     16, 90)
]

SEDES = {
    "Matriz": {
        "foto": "assets/sede-matriz.jpg",
        "map_url": "https://www.google.com/maps/place/Wabi+Sabi+Barber+Matriz/",
        "barberos": [
            {"nombre": "Israel", "foto": "assets/barber-isra.jpg", "rating": 4.9},
            {"nombre": "Josué", "foto": "assets/Josue_SedeMatriz.jpg", "rating": 4.7},
        ],
    },
    "Centro": {
        "foto": "assets/sede-centro.jpg",
        "map_url": "https://www.google.com/maps/place/Wabi+Sabi+Barber+Centro/",
        "barberos": [
            {"nombre": "Santiago", "foto": "assets/Santiago_SedeMatriz.jpg", "rating": 4.6},
            {"nombre": "Wilson", "foto": "assets/Wilson_SedeMatriz.jpg", "rating": 4.8},
        ],
    },
    "Urban": {
        "foto": "assets/logo-1.jpg",
        "map_url": "https://www.google.com/maps/place/Wabi+Sabi+Barber+Urban/",
        "barberos": [
            {"nombre": "Anthony", "foto": "assets/Anthony_SedeUrban.jpg", "rating": 4.6},
        ],
    },
    "Veloz": {
        "foto": "assets/sede-veloz.jpg",
        "map_url": "https://www.google.com/maps/place/Wabi+Sabi+Barber+Veloz/",
        "barberos": [
            {"nombre": "Carlos", "foto": "assets/Kevin_SedeVeloz.jpg", "rating": 4.5},
            {"nombre": "Pablo", "foto": "assets/Marcos_SedeVeloz.jpg", "rating": 4.6},
        ],
    },
    "Barber Training": {
        "foto": "assets/logo-1.jpg",
        "map_url": "https://www.google.com/maps/place/Wabi+Sabi+Barber+Training/",
        "barberos": [
            {"nombre": "José", "foto": "assets/barber-jose.jpg", "rating": 4.8},
        ],
    },
}

# 🖼️ FUNCIÓN AUXILIAR PARA IMÁGENES
def img_tag(path, cls=""):
    p = Path(path)
    if not p.exists():
        return ""
    ext = p.suffix.replace(".", "")
    b64 = base64.b64encode(p.read_bytes()).decode()
    return f"<img src='data:image/{ext};base64,{b64}' class='{cls}'>"

# 🧠 MANEJO DE SESIÓN
for key in ["step", "sede", "servicio", "barbero", "fecha", "hora"]:
    if key not in st.session_state:
        st.session_state[key] = None
if st.session_state.step is None:
    st.session_state.step = 1

# 🪪 ENCABEZADO PRINCIPAL
st.markdown(f"""
<div class="header">
  <img src="data:image/png;base64,{base64.b64encode(open('assets/logo.jpg','rb').read()).decode()}" class="logo">
  <div>
    <h2 class="title">Wabi Sabi Barber</h2>
    <p class="subtitle">📍 Riobamba — Lun–Sáb 09:00–20:00</p>
  </div>
</div>
""", unsafe_allow_html=True)

# 🔢 INDICADOR DE PASOS
st.markdown(f"""
<div class="steps">
  <div class="step {'active' if st.session_state.step == 1 else ''}">1<br><span>Sede</span></div>
  <div class="step {'active' if st.session_state.step == 2 else ''}">2<br><span>Servicio</span></div>
  <div class="step {'active' if st.session_state.step == 3 else ''}">3<br><span>Barbero</span></div>
  <div class="step {'active' if st.session_state.step == 4 else ''}">4<br><span>Fecha y Hora</span></div>
  <div class="step {'active' if st.session_state.step == 5 else ''}">5<br><span>Confirmar</span></div>
</div>
""", unsafe_allow_html=True)

# 🧭 PASO 1: ELEGIR SEDE
if st.session_state.step == 1:
    st.markdown("<h4 class='section-title'>Selecciona una Sede</h4>", unsafe_allow_html=True)
    cols = st.columns(2)
    for i, (sede, data) in enumerate(SEDES.items()):
        with cols[i % 2]:
            st.markdown(f"""
            <div class="card">
                {img_tag(data["foto"], "card-img")}
                <h5>{sede}</h5>
                <a href="{data['map_url']}" target="_blank" class="btn-map">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/9/9b/Google_Maps_icon_%282020%29.svg" class="map-icon">
                    Ver en Google Maps
                </a>
            </div>
            """, unsafe_allow_html=True)
            if st.button(f"Elegir {sede}", key=f"sede_{sede}", use_container_width=True):
                st.session_state.sede = sede
                st.session_state.step = 2
                st.rerun()

# ✂️ PASO 2: ELEGIR SERVICIO
elif st.session_state.step == 2:
    st.markdown("<h4 class='section-title'>Selecciona un Servicio</h4>", unsafe_allow_html=True)
    cols = st.columns(2)
    for i, (nombre, desc, precio, tiempo) in enumerate(SERVICIOS):
        with cols[i % 2]:
            st.markdown(f"""
            <div class="card service-card">
                <h5>{nombre}</h5>
                <p>{desc}</p>
                <p class="text-muted">⏱️ {tiempo} min — 💲{precio}</p>
            </div>
            """, unsafe_allow_html=True)
            if st.button("✂️ Reservar", key=f"servicio_{nombre}", use_container_width=True):
                st.session_state.servicio = nombre
                st.session_state.step = 3
                st.rerun()
    if st.button("← Anterior"):
        st.session_state.step = 1
        st.rerun()

# 💈 PASO 3: ELEGIR BARBERO
elif st.session_state.step == 3:
    sede = st.session_state.sede
    st.markdown(f"<h4 class='section-title'>Barberos en {sede}</h4>", unsafe_allow_html=True)
    cols = st.columns(2)
    for i, b in enumerate(SEDES[sede]["barberos"]):
        with cols[i % 2]:
            st.markdown(f"""
            <div class="card">
                {img_tag(b["foto"], "avatar")}
                <h5>{b["nombre"]}</h5>
                <p>⭐ {b["rating"]}</p>
            </div>
            """, unsafe_allow_html=True)
            if st.button(f"💈 Agendar con {b['nombre']}", key=f"barbero_{b['nombre']}", use_container_width=True):
                st.session_state.barbero = {"nombre": b["nombre"]}
                st.session_state.step = 4
                st.rerun()
    if st.button("← Anterior"):
        st.session_state.step = 2
        st.rerun()

# 🕒 PASO 4: ELEGIR FECHA Y HORA
elif st.session_state.step == 4:
    st.markdown("<h4 class='section-title'>Selecciona una Fecha y Hora</h4>", unsafe_allow_html=True)
    fecha = st.date_input("📆 Fecha disponible", date.today())

    calendar = GoogleCalendar()
    calendar_id = os.getenv("CALENDAR_ID", "mariodanielq.p@gmail.com")
    horas_disponibles = calendar.get_available_hours(calendar_id, fecha)

    if not horas_disponibles:
        st.warning("No hay horarios disponibles para esta fecha.")
    else:
        st.markdown("<h5>⏰ Horarios disponibles</h5>", unsafe_allow_html=True)
        cols = st.columns(4)
        for i, hora in enumerate(horas_disponibles):
            if cols[i % 4].button(hora, key=f"hora_{hora}"):
                st.session_state.hora = hora
                st.session_state.fecha = fecha
                st.session_state.step = 5
                st.rerun()

    if st.button("← Anterior"):
        st.session_state.step = 3
        st.rerun()

# ✅ PASO 5: CONFIRMAR RESERVA
elif st.session_state.step == 5:
    st.markdown("<h4 class='section-title'>Confirmar Reserva</h4>", unsafe_allow_html=True)
    st.markdown(f"""
    <div class="summary-card">
        <h5>Resumen de tu cita</h5>
        <p><b>Servicio:</b> {st.session_state.servicio}</p>
        <p><b>Barbero:</b> {st.session_state.barbero['nombre']}</p>
        <p><b>Sede:</b> {st.session_state.sede}</p>
        <p><b>Fecha:</b> {st.session_state.fecha}</p>
        <p><b>Hora:</b> {st.session_state.hora}</p>
    </div>
    """, unsafe_allow_html=True)

    nombre = st.text_input("👤 Nombre completo")
    telefono = st.text_input("📞 Teléfono")
    email = st.text_input("✉️ Correo electrónico")

    if st.button("✅ Confirmar Reserva", use_container_width=True):
        calendar = GoogleCalendar()
        hora_obj = datetime.strptime(st.session_state.hora, "%H:%M").time()
        calendar.create_event(
            calendar_id,
            nombre, telefono, email,
            st.session_state.servicio,
            st.session_state.fecha,
            hora_obj
        )
        st.success("✅ Reserva confirmada y guardada en Google Calendar")
        st.balloons()

        for k in ["step", "sede", "servicio", "barbero", "fecha", "hora"]:
            st.session_state[k] = None
        st.session_state.step = 1
        st.rerun()
