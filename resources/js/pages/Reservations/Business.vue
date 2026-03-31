<script setup>
import { Link, useForm } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  email: '',
  phone: '',
  branch: '',
  event_date: '',
  event_time: '',
  guests: 1,
  package_name: '',
  room_choice: '',
  food_package: '',
  beverage_package: '',
  event_materials: '',
  notes: '',
})

const submit = () => {
  form.post('/reservations/business')
}
</script>

<template>
  <div class="page">
    <nav class="navbar">
      <div class="logo">McDonald's Reservations</div>
      <div class="nav-links">
        <Link href="/" prefetch>Home</Link>
        <Link href="/reservations/birthday" prefetch>Birthday</Link>
        <Link href="/reservations/business" prefetch>Business</Link>
      </div>
    </nav>

    <section class="hero">
      <div class="form-card">
        <h1>Business Meeting Reservation</h1>

        <form @submit.prevent="submit">
          <label>Full Name</label>
          <input v-model="form.name" type="text" />

          <label>Email</label>
          <input v-model="form.email" type="email" />

          <label>Phone</label>
          <input v-model="form.phone" type="text" />

          <label>Branch</label>
          <input v-model="form.branch" type="text" />

          <div class="two-col">
            <div>
              <label>Event Date</label>
              <input v-model="form.event_date" type="date" />
            </div>
            <div>
              <label>Event Time</label>
              <input v-model="form.event_time" type="time" />
            </div>
          </div>

          <label>Number of Guests</label>
          <input v-model="form.guests" type="number" min="1" />

          <label>Business Package</label>
          <select v-model="form.package_name">
            <option disabled value="">Select package</option>
            <option>Team Meeting Package</option>
            <option>Corporate Lunch Package</option>
            <option>Executive Meeting Package</option>
          </select>

          <label>Room Choice</label>
          <select v-model="form.room_choice">
            <option disabled value="">Select room</option>
            <option>Meeting Room A - Small Room</option>
            <option>Meeting Room B - Medium Room</option>
            <option>Meeting Room C - Large Room</option>
          </select>

          <label>Food Package</label>
          <select v-model="form.food_package">
            <option disabled value="">Select food package</option>
            <option>Burger Meeting Tray</option>
            <option>Chicken Meeting Meal Set</option>
            <option>Mixed Corporate Meal Package</option>
          </select>

          <label>Beverage Package</label>
          <select v-model="form.beverage_package">
            <option disabled value="">Select beverage package</option>
            <option>Coffee and Softdrinks Package</option>
            <option>Juice and Water Package</option>
            <option>Executive Beverage Package</option>
          </select>

          <label>Business Meeting Materials</label>
          <select v-model="form.event_materials">
            <option disabled value="">Select meeting materials</option>
            <option>Projector + Whiteboard + Markers</option>
            <option>Projector + Sound System + Whiteboard</option>
            <option>Full Meeting Set with Projector, Microphone, and Writing Materials</option>
          </select>

          <label>Notes</label>
          <textarea v-model="form.notes" rows="4"></textarea>

          <button type="submit">Submit Business Reservation</button>
        </form>
      </div>
    </section>
  </div>
</template>

<style scoped>
* { box-sizing: border-box; }
.page { min-height: 100vh; background: #fff8e7; }
.navbar { background: #d9230f; color: white; padding: 18px 40px; display: flex; justify-content: space-between; align-items: center; }
.logo { font-size: 28px; font-weight: 800; }
.nav-links a { color: white; text-decoration: none; margin-left: 22px; font-weight: 700; }
.hero {
  min-height: calc(100vh - 72px);
  background:
    linear-gradient(rgba(217, 35, 15, 0.82), rgba(255, 193, 7, 0.78)),
    url('https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&w=1400&q=80') center/cover no-repeat;
  display: flex; align-items: center; justify-content: center; padding: 40px 20px;
}
.form-card { width: 100%; max-width: 760px; background: white; border-radius: 18px; padding: 32px; box-shadow: 0 10px 24px rgba(0, 0, 0, 0.16); }
h1 { text-align: center; color: #d9230f; font-size: 38px; margin-bottom: 20px; font-weight: 900; }
label { display: block; margin-top: 16px; margin-bottom: 6px; font-weight: 800; color: #b71c0c; }
input, select, textarea, button { width: 100%; padding: 12px 14px; border-radius: 10px; border: 1px solid #d9d9d9; font-size: 15px; }
button { margin-top: 24px; background: #d9230f; color: white; font-weight: 800; border: none; cursor: pointer; }
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
</style>
